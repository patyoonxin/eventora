<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;

class EventController
{
    private StripeService $stripeService;

   /*public function registerForEvent(Request $request, Response $response, array $args): Response
    {
        try {
            $db = \App\Models\Database::connect();
            $user = $request->getAttribute('user');
            $eventId = (int)$args['id'];

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

            $existingStmt = $db->prepare("SELECT id FROM tickets WHERE event_id = :event_id AND user_id = :user_id AND status IN ('valid','used') LIMIT 1");
            $existingStmt->execute([
                'event_id' => $eventId,
                'user_id' => $user->id,
            ]);

            if ($existingStmt->fetch()) {
                return $this->errorResponse($response, 'You are already registered for this event', 409);
            }

            $price = (float)$event['price'];
            $parsedBody = $request->getParsedBody() ?: [];
            $paymentMethod = isset($parsedBody['payment_method']) ? trim($parsedBody['payment_method']) : null;

            if ($price > 0 && empty($paymentMethod)) {
                $response->getBody()->write(json_encode([
                    'status' => 'payment_required',
                    'message' => 'Payment is required before registration can be completed.',
                    'payment_required' => true,
                    'price' => $price,
                    'available_payment_methods' => [
                        'FPX',
                        'Touch n Go EWallet',
                        'Credit / Debit Card',
                    ],
                ]));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(402);
            }

            if ($price > 0) {
                $allowedMethods = ['FPX', 'Touch n Go EWallet', 'Credit / Debit Card'];
                if (!in_array($paymentMethod, $allowedMethods, true)) {
                    return $this->errorResponse($response, 'Invalid payment method selected', 400);
                }
            } else {
                $paymentMethod = 'Free';
            }

            if (!empty($event['capacity'])) {
                $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM tickets WHERE event_id = :event_id AND status IN ('valid','used')");
                $countStmt->execute(['event_id' => $eventId]);
                $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
                $reserved = (int)($countResult['total'] ?? 0);

                if ($reserved >= (int)$event['capacity']) {
                    return $this->errorResponse($response, 'Event is full', 409);
                }
            }

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
    }*/
}