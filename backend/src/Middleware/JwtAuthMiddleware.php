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
        // 1. Grab the Authorization header from the request
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorizedResponse("Access token missing or invalid.");
        }

        $jwtToken = $matches[1];

        try {
            // 2. Decode the token using your secret key from the .env file

            // Option 1: Use the config
            $config = require __DIR__ . '/../../config/settings.php';
            $secretKey = $config['jwt']['secret'];
            $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));

            // 3. Extract the user object and attach it securely to the request attributes
            $payload = (array) $decoded;
            $user = $payload['user'] ?? $payload;

            $request = $request->withAttribute('user', (object) $user);

            // 4. Pass the request forward cleanly to your controller
            return $handler->handle($request);
        } catch (\Exception $e) {
            error_log("JWT Decode Error: " . $e->getMessage());
            error_log("Token: " . substr($jwtToken, 0, 50) . "...");
            error_log("Secret used: " . $secretKey);
            return $this->unauthorizedResponse("Session expired or token tamper detected.");
        }
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            "status" => "error",
            "message" => "Unauthorized: " . $message
        ]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}
