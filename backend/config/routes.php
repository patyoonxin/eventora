<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AuthController;
use App\Controllers\EventController;
use App\Middleware\JwtAuthMiddleware;
use App\Controllers\FeedbackController;
use App\Controllers\NotificationController;
use App\Controllers\AnalyticsController;

return function (App $app) {
    // Test endpoint to check database connectivity
    $app->get('/api/test-db', function (Request $request, Response $response) {
        try {
            $db = \App\Models\Database::connect();
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt->fetchAll();

            $payload = json_encode([
                "status" => "success",
                "message" => "Connected to database!",
                "tables_found" => count($tables)
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // PUBLIC ROUTES (No Token Needed)
    $app->post('/api/register', [AuthController::class, 'register']);
    $app->post('/api/login', [AuthController::class, 'login']);
    $app->get('/api/events', [EventController::class, 'index']);
    $app->get('/api/events/{id}/participants/count', [EventController::class, 'getParticipantCount']);

    // PROTECTED ROUTES (Locked Down behind our JWT Check)
    $app->get('/api/users/past-events', [EventController::class, 'getPastEvents'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/users/past-events/{event_id}/certificate', [EventController::class, 'generateCertificate'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/upcoming-events', [EventController::class, 'getSocietyUpcomingEvents'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/events/{id}', [EventController::class, 'getEvent'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/society/events/{id}/update', [EventController::class, 'update'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/society/events/add', [EventController::class, 'add'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/society/events/{id}/cancel', [EventController::class, 'cancel'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/past-events', [EventController::class, 'getSocietyPastEvents'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/events/{id}/feedbacks', [FeedbackController::class, 'getEventFeedbacks'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/events/{id}/feedback-summary', [FeedbackController::class, 'getAiSummary'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/events/{id}/attendance/export', [EventController::class, 'exportAttendance'])
        ->add(new JwtAuthMiddleware());
    
    $app->get('/api/society/events/{id}/participants', [EventController::class, 'getEventParticipants'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/society/events/{id}/send-reminders', [NotificationController::class, 'sendReminders'])
        ->add(new JwtAuthMiddleware());
    
    $app->get('/api/society/analytics', [AnalyticsController::class, 'getSocietyAnalytics'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/admin/pending-events', [EventController::class, 'getPendingEvents'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/admin/events/{id}', [EventController::class, 'getSinglePendingEvent'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/admin/events/{id}/review', [EventController::class, 'reviewEvent'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/admin/all-events', [EventController::class, 'getAllEvents'])
        ->add(new JwtAuthMiddleware());

    $app->delete('/api/admin/events/{id}', [EventController::class, 'deleteEvent'])
        ->add(new JwtAuthMiddleware());
    
    $app->get('/api/admin/analytics', [AnalyticsController::class, 'getAdminAnalytics'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/student/events/{id}', [EventController::class, 'getStudentEvent'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/student/feedback', [FeedbackController::class, 'submitFeedback'])
        ->add(new JwtAuthMiddleware());

    $app->group('/api/notifications', function (RouteCollectorProxy $group) {
        // These paths are relative to the group prefix
        $group->get('', [NotificationController::class, 'getNotifications']);
        $group->put('/{id}/read', [NotificationController::class, 'markAsRead']);
    })->add(new JwtAuthMiddleware()); 

    $app->post('/api/notifications/generate-recommendations', [NotificationController::class, 'generateRecommendations'])
        ->add(new JwtAuthMiddleware());

};
