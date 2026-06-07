<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeedbackController
{
    /**
     * POST /api/student/feedback
     * Submits a student's feedback/rating for a specific event
     */
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
}