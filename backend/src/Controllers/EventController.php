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
            $events = Event::getAllApproved();

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

            // Hardcoded student ID 1 for now until we plug in the JWT authentication middleware
            $userId = 2;

            // Query fetching only events where the user checked in, alongside their ticket number
            $query = "
                SELECT e.*, s.name AS society_name, LPAD(t.id, 6, '0') AS ticket_number
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
}
