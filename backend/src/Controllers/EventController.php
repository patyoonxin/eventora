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
