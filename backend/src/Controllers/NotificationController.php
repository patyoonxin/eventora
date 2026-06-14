<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class NotificationController
{
    // GET /api/notifications
    public function getNotifications(Request $request, Response $response)
    {
        // Grab the user ID attached to the request attributes by your JWT middleware
        $tokenData = $request->getAttribute('user');

        if (!$tokenData || !isset($tokenData->id)) {
            $response->getBody()->write(json_encode([
                "message" => "Unauthorized"
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        $userId = $tokenData->id;

        $db = \App\Models\Database::connect();

        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($notifications));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // PUT /api/notifications/{id}/read
    public function markAsRead(Request $request, Response $response, array $args)
    {
        $notificationId = $args['id'];
        $tokenData = $request->getAttribute('user');

        if (!$tokenData || !isset($tokenData->id)) {
            $response->getBody()->write(json_encode([
                "message" => "Unauthorized"
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        $userId = $tokenData->id;

        $db = \App\Models\Database::connect();

        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);

        $response->getBody()->write(json_encode(['message' => 'Notification marked as read']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function sendReminders(Request $request, Response $response, array $args)
    {
        error_log("sendReminders reached");
        $eventId = $args['id'];

        $db = \App\Models\Database::connect();

        // 1. Get Event details
        $eventStmt = $db->prepare("SELECT title, venue, starts_at FROM events WHERE id = :id");
        $eventStmt->execute(['id' => $eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Event not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // 2. Find all users who bought a ticket for this event
        $ticketStmt = $db->prepare("
                SELECT user_id 
                FROM tickets 
                WHERE event_id = :event_id 
                AND status = 'valid'
            ");
        $ticketStmt->execute(['event_id' => $eventId]);
        $attendees = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Loop through attendees and insert a reminder notification for each
        $insertNotif = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (:user_id, :title, :message, 'reminder')
        ");

        $formattedDate = date('d M Y, h:i A', strtotime($event['starts_at']));

        foreach ($attendees as $attendee) {
            $insertNotif->execute([
                'user_id' => $attendee['user_id'],
                'title'   => "Upcoming Event Reminder: {$event['title']}",
                'message' => "Friendly reminder that '{$event['title']}' is starting soon on {$formattedDate} at {$event['venue']}. See you there!"
            ]);
        }

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Reminders broadcasted to ' . count($attendees) . ' attendees.'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
