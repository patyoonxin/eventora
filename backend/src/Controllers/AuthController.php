<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use Firebase\JWT\JWT;

class AuthController {
    
    // 1. REGISTRATION ENDPOINT (POST /api/register)
    public function register(Request $request, Response $response): Response {
        $body = $request->getParsedBody();

        // Server-side input validation [cite: 26, 28]
        if (empty($body['name']) || empty($body['email']) || empty($body['password'])) {
            $response->getBody()->write(json_encode(["error" => "Missing required fields"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Check if email already exists
        if (User::findByEmail($body['email'])) {
            $response->getBody()->write(json_encode(["error" => "Email is already registered"]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        // Restrict roles to allowed project values [cite: 61]
        $allowedRoles = ['attendee', 'organiser', 'faculty_admin'];
        $role = $body['role'] ?? 'attendee';
        if (!in_array($role, $allowedRoles)) {
            $role = 'attendee';
        }

        // Save to Database via our Model
        $success = User::create([
            'name'     => $body['name'],
            'email'    => $body['email'],
            'password' => $body['password'],
            'role'     => $role
        ]);

        if ($success) {
            $response->getBody()->write(json_encode(["message" => "Registration successful!"]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(["error" => "Something went wrong"]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    // 2. LOGIN ENDPOINT (POST /api/login)
    public function login(Request $request, Response $response): Response {
        $body = $request->getParsedBody();

        if (empty($body['email']) || empty($body['password'])) {
            $response->getBody()->write(json_encode(["error" => "Email and password are required"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $user = User::findByEmail($body['email']);

        // Verify password against secure hash [cite: 14]
        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            $response->getBody()->write(json_encode(["error" => "Invalid email or password"]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Generate the JWT token if credentials match [cite: 15]
        $secretKey = $_ENV['JWT_SECRET'] ?? 'fallback_secret';
        $issuedAt = time();
        $expire = $issuedAt + (int)($_ENV['JWT_EXPIRY'] ?? 3600);

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expire,
            'iss'  => 'eventora_api',
            'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'] // Vital for role-based frontend routing later [cite: 61]
            ]
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        $response->getBody()->write(json_encode([
            "message" => "Login successful!",
            "token"   => $jwt,
            "user"    => $payload['user']
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}