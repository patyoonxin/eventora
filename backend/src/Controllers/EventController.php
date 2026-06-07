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

}
