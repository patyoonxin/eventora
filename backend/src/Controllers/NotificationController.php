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
}
