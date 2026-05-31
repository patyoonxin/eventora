<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Event;

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
    public function getSocietyUpcomingEvents(Request $request, Response $response): Response {
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
}
