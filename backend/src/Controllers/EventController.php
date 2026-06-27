<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use App\Models\Event;
use PDO;

class EventController
{

    // GET /api/events
    public function index(Request $request, Response $response): Response
    {
        try {
            $events = Event::getUpcomingApproved();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $events
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Could not retrieve events: " . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // GET /api/users/past-events
    public function getPastEvents(Request $request, Response $response): Response
    {
        try {
            $db = \App\Models\Database::connect();

            // 👇 READ THE DYNAMIC DATA LOADED FROM THE JWT MIDDLEWARE 👇
            $user = $request->getAttribute('user');
            $userId = $user->id; // Your database field `id` from the payload!

            $query = "
                SELECT e.*, s.name AS society_name, LPAD(t.id, 5, '0') AS ticket_number
                FROM events e
                JOIN societies s ON e.society_id = s.id
                JOIN tickets t ON e.id = t.event_id
                WHERE t.user_id = :user_id 
                AND t.status = 'used'
                ORDER BY e.starts_at DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $userId]);
            $pastEvents = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $pastEvents
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // GET /api/society/upcoming-events
    public function getSocietyUpcomingEvents(Request $request, Response $response): Response
    {
        try {
            $db = \App\Models\Database::connect();

            // 1. Extract user details injected by your working JwtAuthMiddleware
            $user = $request->getAttribute('user');
            $userId = $user->id; // The logged-in organiser's User ID

            // 2. Dynamic Lookup: Find the Society ID managed by this advisor/organiser
            $societyQuery = "SELECT id FROM societies WHERE advisor_id = :user_id LIMIT 1";
            $socStmt = $db->prepare($societyQuery);
            $socStmt->execute(['user_id' => $userId]);
            $society = $socStmt->fetch();

            // Safety check: If this user isn't assigned as an advisor to any society, block them safely
            if (!$society) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Unauthorized: You are not assigned as an advisor to any active society."
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $societyId = $society['id']; // This is your dynamic ID! (e.g., 1, 2, or 5 depending on who logs in)

            // 3. Fetch only UPCOMING events belonging to this dynamic society
            $query = "
                SELECT e.*, s.name AS society_name 
                FROM events e
                JOIN societies s ON e.society_id = s.id
                WHERE e.society_id = :society_id 
                AND e.starts_at >= NOW()
                ORDER BY e.starts_at ASC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute(['society_id' => $societyId]);
            $events = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $events
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = $args['id'];

        $db = \App\Models\Database::connect();

        $stmt = $db->prepare("
        SELECT *
        FROM events
        WHERE id = :id
    ");

        $stmt->execute([
            'id' => $eventId
        ]);

        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Event not found'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $event
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json');
    }

    // POST /api/society/events/add
    public function add(Request $request, Response $response): Response
    {
        try {

            // 1. Database & User
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');

            // 2. Find society managed by user
            $societyStmt = $db->prepare("
            SELECT id
            FROM societies
            WHERE advisor_id = :user_id
            LIMIT 1
        ");

            $societyStmt->execute([
                'user_id' => $user->id
            ]);

            $society = $societyStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$society) {
                return $this->errorResponse(
                    $response,
                    "You are not assigned to any society",
                    403
                );
            }

            $societyId = $society['id'];

            // 3. Get request data
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();

            // 4. Validation
            $requiredFields = [
                'title',
                'description',
                'venue',
                'starts_at'
            ];

            foreach ($requiredFields as $field) {

                if (
                    !isset($data[$field]) ||
                    trim($data[$field]) === ''
                ) {
                    return $this->errorResponse(
                        $response,
                        "Field '{$field}' is required",
                        400
                    );
                }
            }

            // Validate dates
            if (
                !empty($data['ends_at']) &&
                strtotime($data['ends_at']) <= strtotime($data['starts_at'])
            ) {
                return $this->errorResponse(
                    $response,
                    "End date must be later than start date",
                    400
                );
            }

            // Validate capacity
            if (
                isset($data['capacity']) &&
                $data['capacity'] !== '' &&
                (int)$data['capacity'] <= 0
            ) {
                return $this->errorResponse(
                    $response,
                    "Capacity must be greater than 0",
                    400
                );
            }

            // Validate price
            if (
                isset($data['price']) &&
                (float)$data['price'] < 0
            ) {
                return $this->errorResponse(
                    $response,
                    "Price cannot be negative",
                    400
                );
            }

            // 5. Upload image
            $imagePath = null;

            if (
                isset($files['image']) &&
                $files['image']->getError() === UPLOAD_ERR_OK
            ) {

                $uploadedImage = $files['image'];

                $allowedImageTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!in_array(
                    $uploadedImage->getClientMediaType(),
                    $allowedImageTypes
                )) {
                    return $this->errorResponse(
                        $response,
                        "Only JPG, PNG and WEBP images are allowed",
                        400
                    );
                }

                $uploadDir = __DIR__ . '/../../public/uploads/events/images';

                $imagePath = $this->moveUploadedFile(
                    $uploadDir,
                    'uploads/events/images',
                    $uploadedImage
                );
            }

            // 6. Upload document
            $docPath = null;

            if (
                isset($files['document']) &&
                $files['document']->getError() === UPLOAD_ERR_OK
            ) {

                $uploadedDoc = $files['document'];

                $allowedDocTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];

                if (!in_array(
                    $uploadedDoc->getClientMediaType(),
                    $allowedDocTypes
                )) {
                    return $this->errorResponse(
                        $response,
                        "Only PDF, DOC and DOCX files are allowed",
                        400
                    );
                }

                $uploadDir = __DIR__ . '/../../public/uploads/events/docs';

                $docPath = $this->moveUploadedFile(
                    $uploadDir,
                    'uploads/events/docs',
                    $uploadedDoc
                );
            }

            // 7. Insert event
            $stmt = $db->prepare("
            INSERT INTO events (
                society_id,
                title,
                description,
                venue,
                starts_at,
                ends_at,
                capacity,
                price,
                status,
                supporting_document,
                image_path,
                category_tags
            )
            VALUES (
                :society_id,
                :title,
                :description,
                :venue,
                :starts_at,
                :ends_at,
                :capacity,
                :price,
                :status,
                :supporting_document,
                :image_path,
                :category_tags
            )
        ");

            $success = $stmt->execute([
                'society_id' => $societyId,
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'venue' => trim($data['venue']),
                'starts_at' => $data['starts_at'],
                'ends_at' => !empty($data['ends_at'])
                    ? $data['ends_at']
                    : null,
                'capacity' => !empty($data['capacity'])
                    ? (int)$data['capacity']
                    : null,
                'price' => !empty($data['price'])
                    ? (float)$data['price']
                    : 0,
                'status' => 'pending',
                'supporting_document' => $docPath,
                'image_path' => $imagePath,
                'category_tags' => $data['category_tags'] ?? ''
            ]);

            if (!$success) {
                return $this->errorResponse(
                    $response,
                    "Failed to create event",
                    500
                );
            }

            // 8. Get inserted ID
            $eventId = $db->lastInsertId();

            // 9. Success response
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Event created successfully',
                'event_id' => $eventId
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
        } catch (\Exception $e) {

            return $this->errorResponse(
                $response,
                $e->getMessage(),
                500
            );
        }
    }

    // POST /api/society/events/{id}/update
    public function update(Request $request, Response $response, array $args): Response
    {
        $eventId = $args['id'];
        $db = \App\Models\Database::connect();

        $user = $request->getAttribute('user');

        // 1. Get event
        $stmt = $db->prepare("
        SELECT id, society_id, image_path, supporting_document
        FROM events
        WHERE id = :id
    ");

        $stmt->execute(['id' => $eventId]);
        $eventRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$eventRecord) {
            return $this->errorResponse($response, "Event not found", 404);
        }

        // 2. Fix ownership check (IMPORTANT)
        $societyStmt = $db->prepare("
        SELECT id FROM societies WHERE advisor_id = :user_id LIMIT 1
    ");

        $societyStmt->execute([
            'user_id' => $user->id
        ]);

        $society = $societyStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$society || $eventRecord['society_id'] != $society['id']) {
            return $this->errorResponse($response, "Unauthorized", 403);
        }

        // 3. Get form data
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        // 4. Validation
        $requiredFields = ['title', 'description', 'venue', 'starts_at'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return $this->errorResponse(
                    $response,
                    "Field '{$field}' is required",
                    400
                );
            }
        }

        // 5. Existing files
        $imagePath = $eventRecord['image_path'];
        $docPath = $eventRecord['supporting_document'];

        // 6. Image upload
        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {

            $uploadedImage = $files['image'];

            $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($uploadedImage->getClientMediaType(), $allowedImageTypes)) {
                return $this->errorResponse($response, "Invalid image type", 400);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/events/images';

            $imagePath = $this->moveUploadedFile(
                $uploadDir,
                'uploads/events/images',
                $uploadedImage
            );

            $oldImage = 'public/' . $eventRecord['image_path'];

            if (!empty($eventRecord['image_path']) && file_exists($oldImage)) {
                unlink($oldImage);
            }
        }

        // 7. Document upload
        if (isset($files['document']) && $files['document']->getError() === UPLOAD_ERR_OK) {

            $uploadedDoc = $files['document'];

            $allowedDocTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            if (!in_array($uploadedDoc->getClientMediaType(), $allowedDocTypes)) {
                return $this->errorResponse($response, "Invalid document type", 400);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/events/docs';

            $docPath = $this->moveUploadedFile(
                $uploadDir,
                'uploads/events/docs',
                $uploadedDoc
            );

            $oldDoc = 'public/' . $eventRecord['supporting_document'];

            if (!empty($eventRecord['supporting_document']) && file_exists($oldDoc)) {
                unlink($oldDoc);
            }
        }

        // 8. Update DB 
        $stmt = $db->prepare("
        UPDATE events
        SET
            title = :title,
            description = :description,
            venue = :venue,
            starts_at = :starts_at,
            ends_at = :ends_at,
            capacity = :capacity,
            price = :price,
            category_tags = :category_tags,
            image_path = :image_path,
            supporting_document = :supporting_document
        WHERE id = :id
    ");

        $success = $stmt->execute([
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'venue' => trim($data['venue']),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => isset($data['capacity']) ? (int)$data['capacity'] : null,
            'price' => isset($data['price']) ? (float)$data['price'] : 0,
            'category_tags' => $data['category_tags'] ?? '',
            'image_path' => $imagePath,
            'supporting_document' => $docPath,
            'id' => $eventId
        ]);

        if (!$success) {
            return $this->errorResponse($response, "Failed to update event", 500);
        }

        // 9. Response
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Event updated successfully',
            'event_id' => $eventId
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(
        Response $response,
        string $message,
        int $statusCode
    ): Response {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    private function moveUploadedFile(
        string $physicalDirectory,
        string $dbDirectory,
        UploadedFileInterface $uploadedFile
    ): string {

        if (!is_dir($physicalDirectory)) {
            mkdir($physicalDirectory, 0777, true);
        }

        $originalName = pathinfo(
            $uploadedFile->getClientFilename(),
            PATHINFO_FILENAME
        );

        $extension = pathinfo(
            $uploadedFile->getClientFilename(),
            PATHINFO_EXTENSION
        );

        $filename = $originalName . '_' . time() . '_' . uniqid() . '.' . $extension;

        $uploadedFile->moveTo(
            $physicalDirectory . DIRECTORY_SEPARATOR . $filename
        );

        // Store relative URL path only
        return $dbDirectory . '/' . $filename;
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $db = \App\Models\Database::connect();
        $eventId = $args['id'];

        $user = $request->getAttribute('user');

        // 1. Get event
        $stmt = $db->prepare("
            SELECT id, society_id, status
            FROM events
            WHERE id = :id
        ");

        $stmt->execute(['id' => $eventId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            return $this->errorResponse($response, "Event not found", 404);
        }

        // 2. Verify ownership (same logic as your update)
        $societyStmt = $db->prepare("
        SELECT id 
        FROM societies 
        WHERE advisor_id = :user_id 
        LIMIT 1
    ");

        $societyStmt->execute([
            'user_id' => $user->id
        ]);

        $society = $societyStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$society || $event['society_id'] != $society['id']) {
            return $this->errorResponse($response, "Unauthorized", 403);
        }

        // 3. Prevent double cancel
        if ($event['status'] === 'cancelled') {
            return $this->errorResponse($response, "Event already cancelled", 400);
        }

        // 4. Update status
        $update = $db->prepare("
            UPDATE events
            SET status = 'cancelled'
            WHERE id = :id
        ");

        $success = $update->execute(['id' => $eventId]);

        if (!$success) {
            return $this->errorResponse($response, "Failed to cancel event", 500);
        }

        // 5. Response
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Event cancelled successfully'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getPendingEvents(Request $request, Response $response): Response
    {

        // 2. Connect to Database using your static model link
        $db = \App\Models\Database::connect();

        try {
            // Relational SQL query to grab the event details along with the host society name
            // If you want to use the category/price multi-select filters directly on the admin list page:
            $stmt = $db->prepare("
                SELECT e.*, s.name AS society_name 
                FROM events e
                JOIN societies s ON e.society_id = s.id
                WHERE e.status = :status
                ORDER BY e.starts_at ASC
            ");

            $stmt->execute([
                'status' => 'pending'
            ]);

            $pendingEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Return shaped response data structure matching A_Home.vue expectations
            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $pendingEvents
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\PDOException $e) {
            // Safe logging without leaking DB architecture strings to public clients
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Internal Database Error. Please contact systems administrator."
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    // GET SINGLE PENDING EVENT WITH RELATIONAL JOIN
    public function getSinglePendingEvent(Request $request, Response $response, array $args): Response
    {
        //System Administrator Role Verification
        //$tokenData = $request->getAttribute('decoded_token_data'); 
        //if (!$tokenData || $tokenData['role'] !== 'faculty_admin') {
        //    $response->getBody()->write(json_encode(["status" => "error", "message" => "Unauthorized admin access."]));
        //    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        //}

        $eventId = $args['id'];
        $db = \App\Models\Database::connect();

        try {
            $stmt = $db->prepare("
                SELECT e.*, s.name AS society_name 
                FROM events e
                JOIN societies s ON e.society_id = s.id
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $eventId]);
            $event = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                $response->getBody()->write(json_encode(["status" => "error", "message" => "Event parameters not found."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode(["status" => "success", "data" => $event]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(["status" => "error", "message" => "Internal Database Error."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    //2. PROCESS ADMINISTRATIVE APPROVAL OR REJECTION MUTATION
    public function reviewEvent(Request $request, Response $response, array $args): Response
    {
        // Guard Check
        //$tokenData = $request->getAttribute('decoded_token_data'); 
        //if (!$tokenData || $tokenData['role'] !== 'faculty_admin') {
        //    $response->getBody()->write(json_encode(["status" => "error", "message" => "Unauthorized admin access."]));
        //    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        //}

        $eventId = $args['id'];
        $input = json_encode($request->getParsedBody());
        $data = json_decode($input, true);
        $newStatus = $data['status'] ?? null;

        // Input Sanity Check validating against your column ENUM constraints
        if (!in_array($newStatus, ['approved', 'rejected'])) {
            $response->getBody()->write(json_encode(["status" => "error", "message" => "Invalid modification status payload criteria."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = \App\Models\Database::connect();

        try {
            $stmt = $db->prepare("
                UPDATE events 
                SET status = :status 
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $newStatus,
                'id' => $eventId
            ]);

            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Event submission updated to " . $newStatus
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(["status" => "error", "message" => "Database mutation execution error."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function getStudentEvent(Request $request, Response $response, array $args): Response
    {
        $db = \App\Models\Database::connect();
        $eventId = $args['id'];

        try {
            // Only return events student is allowed to see (IMPORTANT)
            $stmt = $db->prepare("
            SELECT id, title
            FROM events
            WHERE id = :id
        ");

            $stmt->execute([
                'id' => $eventId
            ]);

            $event = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Event not found"
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $event
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Server error"
            ]));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // GET /api/society/past-events
    public function getSocietyPastEvents(Request $request, Response $response): Response
    {
        try {
            $db = \App\Models\Database::connect();

            // 1. Extract user details injected by your working JwtAuthMiddleware
            $user = $request->getAttribute('user');
            $userId = $user->id; // The logged-in organiser's User ID

            // 2. Dynamic Lookup: Find the Society ID managed by this advisor/organiser
            $societyQuery = "SELECT id FROM societies WHERE advisor_id = :user_id LIMIT 1";
            $socStmt = $db->prepare($societyQuery);
            $socStmt->execute(['user_id' => $userId]);
            $society = $socStmt->fetch();

            // Safety check: If this user isn't assigned as an advisor to any society, block them safely
            if (!$society) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Unauthorized: You are not assigned as an advisor to any active society."
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $societyId = $society['id']; // This is your dynamic ID! (e.g., 1, 2, or 5 depending on who logs in)

            // 3. Fetch only PAST events belonging to this dynamic society
            $query = "
                SELECT e.*, s.name AS society_name 
                FROM events e
                JOIN societies s ON e.society_id = s.id
                WHERE e.society_id = :society_id 
                AND e.starts_at < NOW()
                ORDER BY e.starts_at DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute(['society_id' => $societyId]);
            $events = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $events
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // GET /api/events/{id}/attendance/export
    public function exportAttendance(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $eventId = $args['id'];

            // 1. Extract user details injected by your working JwtAuthMiddleware
            $user = $request->getAttribute('user');
            $userId = $user->id; // The logged-in organiser's User ID

            // 2. Dynamic Security Check: Find the Society ID managed by this advisor/organiser
            $societyQuery = "SELECT id FROM societies WHERE advisor_id = :user_id LIMIT 1";
            $socStmt = $db->prepare($societyQuery);
            $socStmt->execute(['user_id' => $userId]);
            $society = $socStmt->fetch();

            if (!$society) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Unauthorized: You are not assigned as an advisor to any active society."
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $societyId = $society['id'];

            // 3. Verify that this specific event actually belongs to this organiser's society
            $eventVerifyQuery = "SELECT id FROM events WHERE id = :event_id AND society_id = :society_id LIMIT 1";
            $evStmt = $db->prepare($eventVerifyQuery);
            $evStmt->execute([
                'event_id' => $eventId,
                'society_id' => $societyId
            ]);
            $eventExists = $evStmt->fetch();

            if (!$eventExists) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Unauthorized: You do not have permission to export records for this event."
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // 4. Fetch attendance data using prepared statements to prevent SQL Injection
            // (Note: Table names converted to standard lowercase plural format to match your structure)
            $sql = "SELECT u.name, u.email, c.checked_in_at 
                    FROM check_ins c
                    JOIN tickets t ON c.ticket_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE t.event_id = :event_id 
                    ORDER BY c.checked_in_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute(['event_id' => $eventId]);

            // fetchAll() uses default fetch mode established in your static Database connection class
            $attendanceRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 5. Build the CSV structure in memory
            $stream = fopen('php://temp', 'r+');

            // Add CSV Header Row
            fputcsv($stream, ['Full Name', 'Email Address', 'Checked-In Time']);

            // Add Data Rows
            foreach ($attendanceRecords as $record) {
                fputcsv($stream, $record);
            }

            rewind($stream);
            $csvContent = stream_get_contents($stream);
            fclose($stream);

            // 6. Return the CSV response with appropriate HTTP headers
            $response->getBody()->write($csvContent);
            return $response
                ->withHeader('Content-Type', 'text/csv')
                ->withHeader('Content-Disposition', 'attachment; filename="event_' . $eventId . '_attendance.csv"')
                ->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    public function getEventParticipants(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $eventId = $args['id'];

            $query = "
                SELECT 
                    t.id AS ticket_id,
                    t.status AS ticket_status,
                    t.issued_at,
                    u.id AS user_id,
                    u.name AS user_name,
                    u.email AS user_email
                FROM tickets t
                JOIN users u ON t.user_id = u.id
                WHERE t.event_id = :event_id
                ORDER BY t.issued_at DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute(['event_id' => $eventId]);
            $participants = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 5. Send JSON Response back
            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $participants
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function checkInTicket(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');
            $eventId = (int)$args['id'];
            $payload = $request->getParsedBody();

            $ticketId = isset($payload['ticket_id']) ? (int)$payload['ticket_id'] : null;
            $qrPayload = isset($payload['qr_payload']) ? trim($payload['qr_payload']) : '';

            if (!$ticketId && $qrPayload) {
                if (preg_match('/ticket\/(\d+)/', $qrPayload, $matches)) {
                    $ticketId = (int)$matches[1];
                }
            }

            if (!$ticketId) {
                return $this->errorResponse($response, 'Ticket ID or QR payload is required', 400);
            }

            // Validate organiser owns the event
            $societyStmt = $db->prepare("SELECT id FROM societies WHERE advisor_id = :user_id LIMIT 1");
            $societyStmt->execute(['user_id' => $user->id]);
            $society = $societyStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$society) {
                return $this->errorResponse($response, 'Unauthorized: you are not assigned to any society', 403);
            }

            $eventStmt = $db->prepare("SELECT id, society_id FROM events WHERE id = :event_id AND society_id = :society_id LIMIT 1");
            $eventStmt->execute([
                'event_id' => $eventId,
                'society_id' => $society['id'],
            ]);
            $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                return $this->errorResponse($response, 'Unauthorized: this event is not managed by your society', 403);
            }

            $ticketStmt = $db->prepare("SELECT t.id, t.status, t.event_id, u.name AS user_name, u.email AS user_email FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = :ticket_id AND t.event_id = :event_id LIMIT 1");
            $ticketStmt->execute([
                'ticket_id' => $ticketId,
                'event_id' => $eventId,
            ]);
            $ticket = $ticketStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$ticket) {
                return $this->errorResponse($response, 'Ticket not found for this event', 404);
            }

            if ($ticket['status'] !== 'valid') {
                return $this->errorResponse($response, 'Ticket is not valid for check-in', 400);
            }

            $checkInExists = $db->prepare("SELECT id FROM check_ins WHERE ticket_id = :ticket_id LIMIT 1");
            $checkInExists->execute(['ticket_id' => $ticketId]);
            if ($checkInExists->fetch()) {
                return $this->errorResponse($response, 'Ticket has already been checked in', 409);
            }

            $db->beginTransaction();
            $updateTicket = $db->prepare("UPDATE tickets SET status = 'used' WHERE id = :ticket_id");
            $updateTicket->execute(['ticket_id' => $ticketId]);

            $insertCheckIn = $db->prepare("INSERT INTO check_ins (ticket_id, checked_in_at) VALUES (:ticket_id, NOW())");
            $insertCheckIn->execute(['ticket_id' => $ticketId]);
            $db->commit();

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Student checked in successfully',
                'ticket' => [
                    'ticket_id' => $ticket['id'],
                    'event_id' => $eventId,
                    'student_name' => $ticket['user_name'],
                    'student_email' => $ticket['user_email'],
                    'checked_in_at' => date('Y-m-d H:i:s'),
                ],
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function getParticipantCount(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $eventId = $args['id'];

            $query = "
            SELECT COUNT(*) AS total
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.event_id = :event_id
            AND t.status IN ('valid','used')
        ";

            $stmt = $db->prepare($query);
            $stmt->execute(['event_id' => $eventId]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $count = $result['total'] ?? 0;

            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => (int)$count
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    public function registerForEvent(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');
            $eventId = (int)$args['id'];

            // 1. Validate event exists and is open
            $stmt = $db->prepare("SELECT id, title, venue, starts_at, capacity, price, status FROM events WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $eventId]);
            $event = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                return $this->errorResponse($response, 'Event not found', 404);
            }

            if ($event['status'] !== 'approved') {
                return $this->errorResponse($response, 'Registration is not open for this event', 403);
            }

            if (strtotime($event['starts_at']) <= time()) {
                return $this->errorResponse($response, 'This event has already started or closed registration', 400);
            }

            // 2. Prevent Double Registration
            $existingStmt = $db->prepare("SELECT id FROM tickets WHERE event_id = :event_id AND user_id = :user_id AND status IN ('valid','used') LIMIT 1");
            $existingStmt->execute([
                'event_id' => $eventId,
                'user_id' => $user->id,
            ]);

            if ($existingStmt->fetch()) {
                return $this->errorResponse($response, 'You are already registered for this event', 409);
            }

            // 3. Check Capacity Limits
            if (!empty($event['capacity'])) {
                $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM tickets WHERE event_id = :event_id AND status IN ('valid','used')");
                $countStmt->execute(['event_id' => $eventId]);
                $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
                $reserved = (int)($countResult['total'] ?? 0);

                if ($reserved >= (int)$event['capacity']) {
                    return $this->errorResponse($response, 'Event is full', 409);
                }
            }

            $price = (float)$event['price'];

            // =======================================================
            // STRIPE FLOW FOR PAID EVENTS (NO WEBHOOK STRATEGY)
            // =======================================================
            if ($price > 0) {
                // Replace with your real test secret key from Stripe Dashboard
                \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

                // Define your Vue app's frontend domain URL
                $clientUrl = $_ENV['CLIENT_URL'];

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'mode' => 'payment',
                    // Tucking user_id and event_id into metadata is crucial so we can read it on the success page verification step
                    'metadata' => [
                        'user_id' => $user->id,
                        'event_id' => $eventId
                    ],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'myr',
                            'product_data' => [
                                'name' => "Ticket: " . $event['title'],
                            ],
                            'unit_amount' => (int)($price * 100), // Stripe uses cents ($25.00 -> 2500)
                        ],
                        'quantity' => 1,
                    ]],
                    // We append the dynamic checkout session token to the success URL string
                    'success_url' => $clientUrl . '/checkout-success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => $clientUrl . '/events/' . $eventId,
                ]);

                $response->getBody()->write(json_encode([
                    'status' => 'payment_required',
                    'message' => 'Redirecting to Stripe payment gateway...',
                    'url' => $session->url
                ]));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // =======================================================
            // ORIGINAL FREE REGISTRATION FLOW
            // =======================================================
            $paymentMethod = 'Free';
            $seatNumber = $this->assignSeatNumber($db, $eventId, $event['capacity']);

            $insert = $db->prepare("INSERT INTO tickets (user_id, event_id, status, issued_at, seat_number, payment_method) VALUES (:user_id, :event_id, 'valid', NOW(), :seat_number, :payment_method)");
            $success = $insert->execute([
                'user_id' => $user->id,
                'event_id' => $eventId,
                'seat_number' => $seatNumber,
                'payment_method' => $paymentMethod,
            ]);

            if (!$success) {
                return $this->errorResponse($response, 'Failed to register for event', 500);
            }

            $ticketId = (int)$db->lastInsertId();
            $ticketNumber = str_pad($ticketId, 5, '0', STR_PAD_LEFT);
            $qrPayload = 'eventora://ticket/' . $ticketId;

            $updateQr = $db->prepare("UPDATE tickets SET qr_code = :qr_code WHERE id = :id");
            $updateQr->execute([
                'qr_code' => $qrPayload,
                'id' => $ticketId,
            ]);

            $ticket = [
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'event_id' => $eventId,
                'event_title' => $event['title'],
                'venue' => $event['venue'],
                'starts_at' => $event['starts_at'],
                'issued_at' => date('Y-m-d H:i:s'),
                'status' => 'valid',
                'seat_number' => $seatNumber,
                'payment_method' => $paymentMethod,
                'qr_payload' => $qrPayload,
            ];

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Registration successful',
                'ticket' => $ticket,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function verifyStripePayment(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $sessionId = $queryParams['session_id'] ?? null;

            if (empty($sessionId)) {
                return $this->errorResponse($response, 'Missing Stripe session ID', 400);
            }

            // 1. Ask Stripe for the definitive status of this payment token
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return $this->errorResponse($response, 'Payment verification failed or incomplete', 400);
            }

            // Extract metadata items saved during Step 1
            $userId = (int)$session->metadata->user_id;
            $eventId = (int)$session->metadata->event_id;

            $db = \App\Models\Database::connect();

            // 2. Prevent Double Registration on Refresh
            // To strictly protect this, you should ideally alter your `tickets` schema 
            // to have a `stripe_session_id` column. For now, we will verify by user & event:
            $checkStmt = $db->prepare("SELECT id, qr_code FROM tickets WHERE event_id = :event_id AND user_id = :user_id AND status = 'valid' LIMIT 1");
            $checkStmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
            $existingTicket = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingTicket) {
                // If ticket already exists because they reloaded the success view, simply hand back the existing asset details
                $response->getBody()->write(json_encode([
                    'status' => 'success',
                    'message' => 'Ticket already generated previously.',
                    'ticket_id' => $existingTicket['id'],
                    'qr_payload' => $existingTicket['qr_code']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // 3. Look up capacities to execute standard seat logic allocation
            $evtStmt = $db->prepare("SELECT capacity FROM events WHERE id = :id LIMIT 1");
            $evtStmt->execute(['id' => $eventId]);
            $event = $evtStmt->fetch(\PDO::FETCH_ASSOC);
            $capacity = $event ? $event['capacity'] : null;

            $seatNumber = $this->assignSeatNumber($db, $eventId, $capacity);
            $paymentMethod = 'Stripe Card';

            // 4. Save into database
            $insert = $db->prepare("INSERT INTO tickets (user_id, event_id, status, issued_at, seat_number, payment_method) VALUES (:user_id, :event_id, 'valid', NOW(), :seat_number, :payment_method)");
            $insert->execute([
                'user_id' => $userId,
                'event_id' => $eventId,
                'seat_number' => $seatNumber,
                'payment_method' => $paymentMethod,
            ]);

            $ticketId = (int)$db->lastInsertId();
            $qrPayload = 'eventora://ticket/' . $ticketId;

            // 5. Apply unique QR content code payload details 
            $updateQr = $db->prepare("UPDATE tickets SET qr_code = :qr_code WHERE id = :id");
            $updateQr->execute([
                'qr_code' => $qrPayload,
                'id' => $ticketId,
            ]);

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Payment validated and registration finalized!',
                'ticket_id' => $ticketId,
                'qr_payload' => $qrPayload,
                'seat_number' => $seatNumber
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    private function assignSeatNumber(\PDO $db, int $eventId, $capacity = null): string
    {
        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM tickets WHERE event_id = :event_id AND status IN ('valid','used')");
        $countStmt->execute(['event_id' => $eventId]);
        $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
        $nextIndex = ((int)$countResult['total'] ?? 0) + 1;

        if ($capacity !== null && $capacity > 0 && $nextIndex > (int)$capacity) {
            throw new \Exception('Event is full');
        }

        return $this->formatSeatNumber($nextIndex);
    }

    private function formatSeatNumber(int $seatIndex): string
    {
        $row = chr(ord('A') + floor(($seatIndex - 1) / 10));
        $column = (($seatIndex - 1) % 10) + 1;
        return sprintf('%s%02d', $row, $column);
    }

    public function getUserTickets(Request $request, Response $response): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');

            $query = "
                SELECT
                    t.id AS ticket_id,
                    t.event_id,
                    LPAD(t.id, 5, '0') AS ticket_number,
                    t.status AS ticket_status,
                    t.issued_at,
                    t.seat_number,
                    t.payment_method,
                    t.qr_code,
                    e.title AS event_title,
                    e.venue,
                    e.starts_at,
                    e.ends_at,
                    e.capacity,
                    e.price,
                    e.image_path,
                    e.category_tags,
                    e.status AS event_status
                FROM tickets t
                JOIN events e ON e.id = t.event_id
                WHERE t.user_id = :user_id
                ORDER BY e.starts_at DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $user->id]);
            $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($tickets as &$ticket) {
                $ticket['qr_payload'] = $ticket['qr_code'] ?? 'eventora://ticket/' . $ticket['ticket_id'];
            }

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'data' => $tickets,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function getTicketDetail(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');
            $ticketId = (int)$args['id'];

            $query = "
                SELECT
                    t.id AS ticket_id,
                    t.event_id,
                    LPAD(t.id, 5, '0') AS ticket_number,
                    t.status AS ticket_status,
                    t.issued_at,
                    t.seat_number,
                    t.payment_method,
                    t.qr_code,
                    e.title AS event_title,
                    e.description AS event_description,
                    e.venue,
                    e.starts_at,
                    e.ends_at,
                    e.capacity,
                    e.price,
                    e.image_path,
                    e.category_tags,
                    e.status AS event_status
                FROM tickets t
                JOIN events e ON e.id = t.event_id
                WHERE t.id = :ticket_id
                AND t.user_id = :user_id
                LIMIT 1
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'ticket_id' => $ticketId,
                'user_id' => $user->id,
            ]);

            $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$ticket) {
                return $this->errorResponse($response, 'Ticket not found', 404);
            }

            $ticket['qr_payload'] = $ticket['qr_code'] ?? 'eventora://ticket/' . $ticket['ticket_id'];

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'data' => $ticket,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function cancelTicket(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');
            $ticketId = (int)$args['id'];

            $stmt = $db->prepare("SELECT t.id, t.event_id, t.status, e.starts_at FROM tickets t JOIN events e ON e.id = t.event_id WHERE t.id = :ticket_id AND t.user_id = :user_id LIMIT 1");
            $stmt->execute([
                'ticket_id' => $ticketId,
                'user_id' => $user->id,
            ]);

            $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$ticket) {
                return $this->errorResponse($response, 'Ticket not found', 404);
            }

            if ($ticket['status'] !== 'valid') {
                return $this->errorResponse($response, 'Only active tickets can be cancelled', 400);
            }

            if (strtotime($ticket['starts_at']) <= time()) {
                return $this->errorResponse($response, 'Cannot cancel ticket after the event has started', 400);
            }

            $update = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = :ticket_id");
            $update->execute(['ticket_id' => $ticketId]);

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Ticket cancelled successfully',
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function generateCertificate(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();

            // READ THE DYNAMIC DATA LOADED FROM THE JWT MIDDLEWARE 
            $user = $request->getAttribute('user');
            $userId = $user->id;

            // Grab the specific event ID from the route arguments
            $eventId = $args['event_id'];

            // Securely verify attendance using prepared statements
            $query = "
                SELECT u.name AS student_name, e.title AS event_title, e.ends_at, LPAD(t.id, 5, '0') AS ticket_number
                FROM tickets t
                JOIN users u ON t.user_id = u.id
                JOIN events e ON t.event_id = e.id
                WHERE t.user_id = :user_id 
                AND t.event_id = :event_id
                AND t.status = 'used'
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'user_id' => $userId,
                'event_id' => $eventId
            ]);
            $attendance = $stmt->fetch(\PDO::FETCH_OBJ); // Fetching as an object to match your payload style

            // Guard Clause: If no record is found or they didn't attend, return 403 Forbidden
            if (!$attendance) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Access denied. You either did not attend this event or do not hold a valid ticket."
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // --- PDF GENERATION WITH FPDF ---
            // 'L' sets Landscape mode (A4 width is 297mm, height is 210mm)
            $pdf = new \FPDF('L', 'mm', 'A4');
            $pdf->AddPage();

            // Simple Elegant Certificate Layout 
            // Outer Border
            $pdf->SetLineWidth(1);
            $pdf->Rect(10, 10, 277, 190);
            // Inner Border
            $pdf->SetLineWidth(0.5);
            $pdf->Rect(13, 13, 271, 184);

            // Header Title
            $pdf->SetFont('Arial', 'B', 30);
            $pdf->Ln(25);
            $pdf->Cell(0, 15, 'CERTIFICATE OF ATTENDANCE', 0, 1, 'C');

            // Subtitle
            $pdf->SetFont('Arial', '', 16);
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'This is proudly presented to', 0, 1, 'C');

            // Student Name
            $pdf->SetFont('Arial', 'B', 24);
            $pdf->Ln(5);
            $pdf->Cell(0, 15, strtoupper($attendance->student_name), 0, 1, 'C');

            // Description Context
            $pdf->SetFont('Arial', '', 16);
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'for actively participating in the society event:', 0, 1, 'C');

            // Event Title
            $pdf->SetFont('Arial', 'I', 20);
            $pdf->Ln(5);
            $pdf->Cell(0, 12, '"' . $attendance->event_title . '"', 0, 1, 'C');

            // Footer Data (Date and ID validation)
            $pdf->SetFont('Arial', '', 12);
            $pdf->Ln(20);
            $formattedDate = date('d F Y', strtotime($attendance->ends_at));
            $pdf->Cell(0, 10, 'Date Verified: ' . $formattedDate, 0, 1, 'C');

            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 10, 'Verification ID: ' . $attendance->ticket_number, 0, 1, 'C');

            // Stream PDF output cleanly via Slim 4 Response object
            $stream = fopen('php://memory', 'w+');
            fwrite($stream, $pdf->Output('S'));
            rewind($stream);

            return $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="Certificate_' . $eventId . '.pdf"')
                ->withBody(new \Slim\Psr7\Stream($stream));
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Failed to generate certificate: " . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getAllEvents(Request $request, Response $response): Response
    {
        $db = \App\Models\Database::connect();

        try {
            $user = $request->getAttribute('user');
            if (!$user || $user->role !== 'faculty_admin') { // Matches User(id, name, email, password_hash, role) schema [cite: 69]
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Access denied. Admin privileges required."
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }

            // Relational SQL query to grab ALL event statuses along with the host society name [cite: 70, 71]
            $stmt = $db->prepare("
                SELECT e.*, s.name AS society_name 
                FROM events e
                JOIN societies s ON e.society_id = s.id
                ORDER BY e.starts_at ASC
            ");

            // 🚨 FIX: You must explicitly execute the statement before fetching!
            $stmt->execute();

            // Naming changed to $allEvents since it includes approved/pending/cancelled
            $allEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Return shaped response data structure matching A_Home.vue expectations
            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => $allEvents
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\PDOException $e) {
            // Safe logging without leaking DB architecture strings to public clients [cite: 28]
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Internal Database Error. Please contact systems administrator."
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    public function deleteEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = $args['id'];

        $db = \App\Models\Database::connect();

        try {
            $user = $request->getAttribute('user');
            if (!$user || $user->role !== 'faculty_admin') {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Access denied. Admin privileges required."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // 3. Prepare the DELETE query using safe PDO bound parameters
            $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
            $stmt->execute(['id' => $eventId]);

            // Check if any rows were actually affected/removed from the table
            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Target event could not be found or was already deleted."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(44);
            }

            // 4. Return successful execution structure matching your frontend's layout expectations
            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Event record permanently wiped from storage."
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            // Handle Foreign Key Constraint Failures gracefully 
            // (e.g., if tickets are still attached to this event, preventing safe hard deletion)
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Cannot delete event. Student tickets are already attached to this record."
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
