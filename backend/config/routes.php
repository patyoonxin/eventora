<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AuthController;
use App\Controllers\EventController;
use App\Middleware\JwtAuthMiddleware;

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

    // PROTECTED ROUTES (Locked Down behind our JWT Check)
    $app->get('/api/users/past-events', [EventController::class, 'getPastEvents'])
        ->add(new JwtAuthMiddleware()); // This line locks the route down

    $app->get('/api/society/upcoming-events', [EventController::class, 'getSocietyUpcomingEvents'])
        ->add(new JwtAuthMiddleware());

    $app->get('/api/society/events/{id}', [EventController::class, 'getEvent'])
        ->add(new JwtAuthMiddleware());

    $app->post('/api/society/events/{id}/update',[EventController::class, 'update'])
        ->add(new JwtAuthMiddleware());
};
