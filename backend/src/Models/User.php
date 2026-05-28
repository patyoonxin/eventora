<?php

namespace App\Models;

use App\Models\Database;
use PDO;

class User {
    // Find a user by their email (essential for login and checking duplicates)
    public static function findByEmail(string $email): ?array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ? $user : null;
    }

    // Create a new user with a securely hashed password
    public static function create(array $data): bool {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (:name, :email, :password_hash, :role)
        ");
        
        return $stmt->execute([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT), // Required secure hashing [cite: 14]
            'role'          => $data['role'] ?? 'attendee' // Defaults to attendee [cite: 61]
        ]);
    }
}