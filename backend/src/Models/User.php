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

    // Find a user by their ID
    public static function findById(int $id): ?array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
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

    public static function updateAvatar(int $id, string $avatarPath): bool {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE users SET profile_picture = :profile_picture WHERE id = :id
        ");
        return $stmt->execute([
            'profile_picture' => $avatarPath,
            'id'              => $id
        ]);
    }

    public static function updateProfile(int $id, array $data): bool {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE users SET name = :name, email = :email WHERE id = :id
        ");
        return $stmt->execute([
            'name'  => $data['name'],
            'email' => $data['email'],
            'id'    => $id
        ]);
    }

    public static function updatePassword(string $emailOrId, string $newPassword): void
    {
    $db = Database::connect();
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    // Works with email
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$hash, $emailOrId]);
    }
}