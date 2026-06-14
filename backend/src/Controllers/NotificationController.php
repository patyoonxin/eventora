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

    public function generateRecommendations(Request $request, Response $response)
    {
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

        // 1. Get last attended event (based on valid check-in)
        $lastEventQuery = "
        SELECT e.id, e.society_id
        FROM check_ins c
        JOIN tickets t ON c.ticket_id = t.id
        JOIN events e ON t.event_id = e.id
        WHERE t.user_id = :user_id
        ORDER BY c.checked_in_at DESC
        LIMIT 1
    ";

        $stmt = $db->prepare($lastEventQuery);
        $stmt->execute(['user_id' => $userId]);
        $lastEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Build optional society filter
        $societyFilter = $lastEvent ? "AND e.society_id = :society_id" : "";

        // 3. Find recommended event
        $recommendQuery = "
        SELECT e.id, e.title, s.name AS society_name
        FROM events e
        JOIN societies s ON e.society_id = s.id
        WHERE e.status = 'approved'
          AND e.starts_at > NOW()
          {$societyFilter}
          AND e.id NOT IN (
              SELECT event_id FROM tickets WHERE user_id = :user_id
          )
        ORDER BY e.starts_at ASC
        LIMIT 1
    ";

        $stmt = $db->prepare($recommendQuery);

        $params = [
            'user_id' => $userId
        ];

        if ($lastEvent) {
            $params['society_id'] = $lastEvent['society_id'];
        }

        $stmt->execute($params);
        $recommendedEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. No recommendation case
        if (!$recommendedEvent) {
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'No new recommendations available at this time.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        // 5. Prevent duplicate notifications
        $checkNotif = $db->prepare("
        SELECT id FROM notifications 
        WHERE user_id = :user_id 
        AND type = 'recommendation'
        AND message LIKE :match
    ");

        $checkNotif->execute([
            'user_id' => $userId,
            'match' => "%{$recommendedEvent['title']}%"
        ]);

        if ($checkNotif->fetch()) {
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Recommendation already sent previously.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        // 6. Insert notification
        $insertNotif = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (:user_id, :title, :message, 'recommendation')
    ");

        $insertNotif->execute([
            'user_id' => $userId,
            'title' => 'Recommended For You ✨',
            'message' => "Based on your past attendance, we think you'd love '{$recommendedEvent['title']}' hosted by {$recommendedEvent['society_name']}!"
        ]);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Recommendation notification generated!'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }
}
