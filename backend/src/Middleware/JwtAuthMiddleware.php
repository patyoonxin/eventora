<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {

        // 🔴 DEBUG LOGS (PUT HERE)
    error_log("==== JWT MIDDLEWARE HIT ====");
    error_log("METHOD: " . $request->getMethod());
    error_log("AUTH HEADER: " . $request->getHeaderLine('Authorization'));
        // Get Authorization header
        $authHeader = $request->getHeaderLine('Authorization');

        error_log("AUTH HEADER: " . $authHeader);

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse("Access token missing or invalid.");
        }

        $jwtToken = $matches[1];

        error_log("TOKEN RECEIVED: " . $jwtToken);

        try {

            // Load JWT secret
            $config = require __DIR__ . '/../../config/settings.php';
            $secretKey = $config['jwt']['secret'];

            error_log("SECRET KEY: " . $secretKey);

            // Decode JWT
            $decoded = JWT::decode(
                $jwtToken,
                new Key($secretKey, 'HS256')
            );

            // Extract user payload
            $payload = (array) $decoded;
            $user = $payload['user'] ?? $payload;

            // Attach user to request
            $request = $request->withAttribute(
                'user',
                (object) $user
            );

            // Continue request
            return $handler->handle($request);

        } catch (\Exception $e) {

            error_log("JWT ERROR: " . $e->getMessage());

            return $this->unauthorizedResponse(
                $e->getMessage()
            );
        }
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();

        $response->getBody()->write(json_encode([
            "status" => "error",
            "message" => "Unauthorized: " . $message
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}