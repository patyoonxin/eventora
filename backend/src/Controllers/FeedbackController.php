<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeedbackController
{
    public function submitFeedback(Request $request, Response $response): Response
    {
        // 1. Guard Check: Ensure the user is authenticated from the JWT attribute middleware
        $tokenData = $request->getAttribute('user');
        if (!$tokenData) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Access Denied: Missing or corrupted session token signature."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // 2. Decode body inputs sent by the frontend
        $input = json_encode($request->getParsedBody());
        $data = json_decode($input, true);

        $eventId  = $data['event_id'] ?? null;
        $rating   = isset($data['rating']) ? intval($data['rating']) : null;
        $comments = $data['comments'] ?? '';
        $userId = $tokenData->id;

        // 3. Input Validation Constraints
        if (!$eventId || !$rating) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Missing required fields: event_id and rating are mandatory."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($rating < 1 || $rating > 5) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Invalid criteria value: Rating metric must range strictly between 1 and 5."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // 4. Connect and Execute secure PDO statements
        $db = \App\Models\Database::connect();

        try {
            // Check if this user has already left feedback for this event
            $checkStmt = $db->prepare("
                SELECT id FROM feedbacks WHERE user_id = :user_id AND event_id = :event_id
            ");
            $checkStmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
            if ($checkStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "You have already submitted a feedback evaluation for this program."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409); // 409 Conflict
            }

            // Write insertion command safely using named prepared parameters
            $sql = "INSERT INTO feedbacks (event_id, user_id, rating, comment) 
                    VALUES (:event_id, :user_id, :rating, :comments)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'event_id' => $eventId,
                'user_id'  => $userId,
                'rating'   => $rating,
                'comments' => $comments
            ]);

            // 5. Shaped Success Output response
            $response->getBody()->write(json_encode([
                "status" => "success",
                "message" => "Feedback metrics archived successfully!"
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Internal Database mutation exception occurred."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /api/society/events/{id}/feedbacks
     * Fetches individual feedback lines and summaries for a specific society event
     */
    public function getEventFeedbacks(Request $request, Response $response, array $args): Response
    {
        // 1. Authenticate using your existing token system
        $tokenData = $request->getAttribute('user');
        if (!$tokenData) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Access Denied: Missing session parameters."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Target the id from the path parameters (e.g., /events/{id}/feedbacks)
        $eventId = $args['id'] ?? null;
        $userId = $tokenData->id; 
        $userRole = $tokenData->role; // Pulls the role if you have it, defaults to society

        if ($userRole !== 'organiser') {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Forbidden: Student profiles cannot pull dashboard analytics."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $db = \App\Models\Database::connect();

        try {
            // 2. Ownership Verification: Ensure this event belongs to the logged-in society user
            $verifySql = "SELECT e.id, e.title 
                          FROM events e
                          JOIN societies s ON e.society_id = s.id
                          WHERE e.id = :event_id AND s.id = :user_id";
            
            $verifyStmt = $db->prepare($verifySql);
            $verifyStmt->execute([
                'event_id' => $eventId,
                'user_id'  => $userId
            ]);
            $eventMeta = $verifyStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$eventMeta) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Resource not found, or you lack organizational permission bounds."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // 3. Fetch collective feedback submissions grouped with student names
            $feedbackSql = "SELECT 
                                f.id,
                                f.rating,
                                f.comment,
                                f.created_at,
                                u.name AS student_name
                            FROM feedbacks f
                            JOIN users u ON f.user_id = u.id
                            WHERE f.event_id = :event_id
                            ORDER BY f.created_at DESC";

            $feedbackStmt = $db->prepare($feedbackSql);
            $feedbackStmt->execute(['event_id' => $eventId]);
            $feedbacks = $feedbackStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Write perfectly formatted payload into your stream structure
            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => [
                    "event_title" => $eventMeta['title'],
                    "feedbacks"   => $feedbacks
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Internal Database processing exception occurred."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

   public function getAiSummary(Request $request, Response $response, array $args): Response
    {
        // 1. Authenticate using your existing token system
        $tokenData = $request->getAttribute('user');
        if (!$tokenData) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Access Denied: Missing session parameters."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $eventId = $args['id'] ?? null;
        $userId = $tokenData->id; 
        $userRole = $tokenData->role; 

        if ($userRole !== 'organiser') {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Forbidden: Student profiles cannot pull dashboard analytics."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $db = \App\Models\Database::connect();

        try {
            // 2. Ownership Verification
            $verifySql = "SELECT e.id, e.title FROM events e JOIN societies s ON e.society_id = s.id WHERE e.id = :event_id AND s.id = :user_id";
            $verifyStmt = $db->prepare($verifySql);
            $verifyStmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
            $eventMeta = $verifyStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$eventMeta) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Resource not found, or you lack organizational permission bounds."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // 3. Fetch collective text feedback submissions
            $feedbackSql = "SELECT f.rating, f.comment FROM feedbacks f WHERE f.event_id = :event_id AND f.comment IS NOT NULL AND f.comment != ''";
            $feedbackStmt = $db->prepare($feedbackSql);
            $feedbackStmt->execute(['event_id' => $eventId]);
            $feedbacks = $feedbackStmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($feedbacks)) {
                $response->getBody()->write(json_encode([
                    "status" => "success",
                    "data" => ["summary" => "Not enough text feedback has been provided yet to generate an AI summary."]
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // 4. Format prompt
            $feedbackText = "";
            foreach ($feedbacks as $index => $fb) {
                $feedbackText .= ($index + 1) . ". [Rating: " . $fb['rating'] . "/5] - " . $fb['comment'] . "\n";
            }
            $prompt = "You are an AI Event Analytics Assistant for UTM Student Societies. Summarize the following feedback comments submitted by attendees. Provide a brief breakdown of what went well, what needs improvement, and actionable bullet-point suggestions for future event organizers. Keep it brief and constructive.\n\nFeedback:\n" . $feedbackText;

            // =================================================================
            // 5. USE THE NEW AI SERVICE
            // =================================================================
            $gemini = new \App\Services\GeminiService();
            $aiSummaryText = $gemini->generateText($prompt);

            if ($aiSummaryText === null) {
                $response->getBody()->write(json_encode([
                    "status" => "error",
                    "message" => "Failed to communicate with AI processing core engine."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            // =================================================================

            // 6. Return response
            $response->getBody()->write(json_encode([
                "status" => "success",
                "data" => ["summary" => $aiSummaryText]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Internal Database processing exception occurred."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}