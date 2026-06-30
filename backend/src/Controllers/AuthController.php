<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController
{

    // 1. REGISTRATION ENDPOINT (POST /api/register)
    public function register(Request $request, Response $response): Response
    {
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
    public function login(Request $request, Response $response): Response
{
    $body = $request->getParsedBody();

    if (empty($body['email']) || empty($body['password'])) {
        $response->getBody()->write(json_encode(["error" => "Email and password are required"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $user = User::findByEmail($body['email']);

    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        $response->getBody()->write(json_encode(["error" => "Invalid email or password"]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $config = require __DIR__ . '/../../config/settings.php';
    $secretKey = $config['jwt']['secret'];
    $baseUrl = rtrim($config['app_url'] ?? 'http://localhost', '/');
    $issuedAt = time();
    $expire = $issuedAt + (int)($config['jwt']['expiry'] ?? 3600);

    $payload = [
        'iat'  => $issuedAt,
        'exp'  => $expire,
        'iss'  => 'eventora_api',
        'user' => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]
    ];

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    $response->getBody()->write(json_encode([
        "message" => "Login successful!",
        "token"   => $jwt,
        "user"    => [
            'id'              => $user['id'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'role'            => $user['role'],
            'profile_picture' => $user['profile_picture']
                                ? $baseUrl . '/public/' . $user['profile_picture']
                                : null,
        ]
    ]));

    return $response->withHeader('Content-Type', 'application/json');
}

    // 3. GET PROFILE ENDPOINT (GET /api/profile)
    public function getProfile(Request $request, Response $response): Response
    {
        $user = $this->getAuthUser($request);

        $config  = require __DIR__ . '/../../config/settings.php';
        $baseUrl = rtrim($config['app_url'] ?? 'http://localhost', '/');

        $response->getBody()->write(json_encode([
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'profile_picture' => $user['profile_picture']
                                ? $baseUrl . '/' . $user['profile_picture']
                                : null,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $user = $this->getAuthUser($request);
        $data = $request->getParsedBody(); // Slim parses JSON body automatically

        User::updateProfile($user['id'], [
            'name'  => $data['name']  ?? $user['name'],
            'email' => $data['email'] ?? $user['email'],
        ]);

        $response->getBody()->write(json_encode(['message' => 'Profile updated']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function uploadAvatar(Request $request, Response $response): Response
    {
        $user = $this->getAuthUser($request);

        // Get uploaded files via PSR-7 (not $_FILES)
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['avatar'])) {
            $response->getBody()->write(json_encode(['error' => 'No file uploaded']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $file     = $uploadedFiles['avatar'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $tmpPath  = $file->getStream()->getMetadata('uri'); // get the temp path
        $mimeType = mime_content_type($tmpPath);

        if (!in_array($mimeType, $allowed)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid file type']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Build save path
        $ext      = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        $saveDir  = __DIR__ . '/../../public/uploads/avatars/';

        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        // PSR-7 moveTo() instead of move_uploaded_file()
        $file->moveTo($saveDir . $filename);

        // Persist to DB and return URL
        $relativePath = 'uploads/avatars/' . $filename;
        User::updateAvatar($user['id'], $relativePath);

        $config  = require __DIR__ . '/../../config/settings.php';
        $baseUrl = rtrim($config['app_url'] ?? 'http://localhost', '/');

        $response->getBody()->write(json_encode([
            'profile_picture' => $baseUrl . '/' . $relativePath
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function changePassword(Request $request, Response $response): Response
{
    $user = $this->getAuthUser($request);
    $data = $request->getParsedBody();

    if (empty($data['current_password']) || empty($data['new_password'])) {
        $response->getBody()->write(json_encode(['error' => 'Both passwords are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Verify current password
    $fullUser = User::findByEmail($user['email']);
    if (!password_verify($data['current_password'], $fullUser['password_hash'])) {
        $response->getBody()->write(json_encode(['error' => 'Current password is incorrect']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // Update to new password
    User::updatePassword($user['email'], $data['new_password']);

    $response->getBody()->write(json_encode(['message' => 'Password updated successfully']));
    return $response->withHeader('Content-Type', 'application/json');
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
    $data = $request->getParsedBody();
    $email = $data['email'] ?? '';

    if (!$email) {
        $response->getBody()->write(json_encode(['error' => 'Email is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $user = User::findByEmail($email);
    if (!$user) {
        $response->getBody()->write(json_encode(['message' => 'If that email exists, a reset link has been sent.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $token = bin2hex(random_bytes(32));


    $db = \App\Models\Database::connect();
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
    ->execute([$email, $token]);

    $resetUrl = 'http://localhost:5173/reset-password?token=' . $token;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = (int)$_ENV['MAIL_PORT'];
        $mail->setFrom($_ENV['MAIL_FROM'], 'EventOra');
        $mail->addAddress($email);
        $mail->Subject = 'Reset your EventOra password';
        $mail->Body    = "Click the link below to reset your password:\n\n$resetUrl\n\nThis link expires in 1 hour.";
        $mail->send();
    } catch (\Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Failed to send email: ' . $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['message' => 'If that email exists, a reset link has been sent.']));
    return $response->withHeader('Content-Type', 'application/json');
}

public function resetPassword(Request $request, Response $response): Response
{
    $data = $request->getParsedBody();
    $token = $data['token'] ?? '';
    $newPassword = $data['new_password'] ?? '';

    if (!$token || !$newPassword) {
        $response->getBody()->write(json_encode(['error' => 'Token and new password are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (strlen($newPassword) < 8) {
        $response->getBody()->write(json_encode(['error' => 'Password must be at least 8 characters']));
        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$reset) {
        $response->getBody()->write(json_encode(['error' => 'Invalid or expired reset link']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    User::updatePassword($reset['email'], $newPassword);

    // Delete used token
    $db->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);

    $response->getBody()->write(json_encode(['message' => 'Password reset successfully']));
    return $response->withHeader('Content-Type', 'application/json');
}

// 4. GET ALL USERS (ADMIN ONLY - GET /api/admin/users)
public function getAllUsers(Request $request, Response $response): Response
{
    $db = \App\Models\Database::connect();
    $stmt = $db->query("SELECT id, name, email, role, profile_picture, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $config  = require __DIR__ . '/../../config/settings.php';
    $baseUrl = rtrim($config['app_url'] ?? 'http://localhost', '/');

    $users = array_map(function($u) use ($baseUrl) {
        $u['profile_picture'] = $u['profile_picture'] ? $baseUrl . '/' . $u['profile_picture'] : null;
        return $u;
    }, $users);

    $response->getBody()->write(json_encode($users));
    return $response->withHeader('Content-Type', 'application/json');
}

public function createUser(Request $request, Response $response): Response
{
    $data = $request->getParsedBody();

    if (empty($data['name']) || empty($data['email']) || empty($data['role'])) {
        $response->getBody()->write(json_encode(['error' => 'Name, email and role are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (User::findByEmail($data['email'])) {
        $response->getBody()->write(json_encode(['error' => 'Email already exists']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }

    $allowedRoles = ['attendee', 'organiser', 'faculty_admin'];
    if (!in_array($data['role'], $allowedRoles)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid role']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Default password — user should reset via forgot password
    $success = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => 'EventOra2026!',
        'role'     => $data['role'],
    ]);

    if ($success) {
        $response->getBody()->write(json_encode(['message' => 'User created successfully']));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Failed to create user']));
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
}

public function getAllSocieties(Request $request, Response $response): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can view societies']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->query('SELECT id, name, faculty, advisor_id, created_at FROM societies ORDER BY id ASC');
    $societies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($societies));
    return $response->withHeader('Content-Type', 'application/json');
}

public function createSociety(Request $request, Response $response): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can create societies']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data = $request->getParsedBody();

    $name = trim((string)($data['name'] ?? ''));
    $faculty = trim((string)($data['faculty'] ?? ''));
    $advisorId = isset($data['advisor_id']) && $data['advisor_id'] !== '' ? (int)$data['advisor_id'] : null;

    if ($name === '' || $faculty === '') {
        $response->getBody()->write(json_encode(['error' => 'Name and faculty are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($advisorId !== null && $advisorId <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Advisor ID must be a valid user ID']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($advisorId !== null) {
        $db = \App\Models\Database::connect();
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$advisorId]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Advisor user not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare('INSERT INTO societies (name, faculty, advisor_id) VALUES (?, ?, ?)');
    $success = $stmt->execute([$name, $faculty, $advisorId]);

    if ($success) {
        $societyId = (int)$db->lastInsertId();
        $createdStmt = $db->prepare('SELECT id, name, faculty, advisor_id, created_at FROM societies WHERE id = ?');
        $createdStmt->execute([$societyId]);
        $society = $createdStmt->fetch(\PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'message' => 'Society created successfully',
            'society' => $society,
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Failed to create society']));
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
}

public function getAllOrganisers(Request $request, Response $response): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can manage organisers']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE role = 'organiser' ORDER BY name ASC");
    $stmt->execute();
    $organisers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($organisers));
    return $response->withHeader('Content-Type', 'application/json');
}

public function getSocietyOrganisers(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can manage organisers']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $societyId = (int)($args['id'] ?? 0);
    if ($societyId <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Invalid society id']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare("SELECT u.id, u.name, u.email FROM society_organisers so JOIN users u ON u.id = so.user_id WHERE so.society_id = ? ORDER BY u.name ASC");
    $stmt->execute([$societyId]);
    $organisers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($organisers));
    return $response->withHeader('Content-Type', 'application/json');
}

public function assignSocietyOrganiser(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can manage organisers']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $societyId = (int)($args['id'] ?? 0);
    $data = $request->getParsedBody();
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

    if ($societyId <= 0 || $userId <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Invalid society or organiser id']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $societyStmt = $db->prepare('SELECT id FROM societies WHERE id = ? LIMIT 1');
    $societyStmt->execute([$societyId]);
    if (!$societyStmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Society not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $organiserStmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'organiser' LIMIT 1");
    $organiserStmt->execute([$userId]);
    $organiser = $organiserStmt->fetch(\PDO::FETCH_ASSOC);
    if (!$organiser) {
        $response->getBody()->write(json_encode(['error' => 'Organiser user not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $insertStmt = $db->prepare('INSERT INTO society_organisers (society_id, user_id) VALUES (?, ?)');
    try {
        $insertStmt->execute([$societyId, $userId]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            $response->getBody()->write(json_encode(['error' => 'This organiser is already assigned to this society']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
        throw $e;
    }

    $response->getBody()->write(json_encode([
        'message' => 'Organiser assigned successfully',
        'organiser' => $organiser,
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
}

public function removeSocietyOrganiser(Request $request, Response $response, array $args): Response
{
    $user = $request->getAttribute('user');
    if (!$user || !isset($user->role) || $user->role !== 'faculty_admin') {
        $response->getBody()->write(json_encode(['error' => 'Only faculty admins can manage organisers']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $societyId = (int)($args['id'] ?? 0);
    $userId = (int)($args['user_id'] ?? 0);

    if ($societyId <= 0 || $userId <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Invalid society or organiser id']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare('DELETE FROM society_organisers WHERE society_id = ? AND user_id = ? LIMIT 1');
    $success = $stmt->execute([$societyId, $userId]);

    if (!$success || $stmt->rowCount() === 0) {
        $response->getBody()->write(json_encode(['error' => 'Assigned organiser not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['message' => 'Organiser removed successfully']));
    return $response->withHeader('Content-Type', 'application/json');
}

public function updateUserRole(Request $request, Response $response, array $args): Response
{
    $id   = (int) $args['id'];
    $data = $request->getParsedBody();
    $role = $data['role'] ?? '';

    $allowedRoles = ['attendee', 'organiser', 'faculty_admin'];
    if (!in_array($role, $allowedRoles)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid role']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = \App\Models\Database::connect();
    $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);

    $response->getBody()->write(json_encode(['message' => 'Role updated']));
    return $response->withHeader('Content-Type', 'application/json');
}

public function deleteUser(Request $request, Response $response, array $args): Response
{
    $id = (int) $args['id'];

    $db = \App\Models\Database::connect();
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $response->getBody()->write(json_encode(['message' => 'User deleted']));
    return $response->withHeader('Content-Type', 'application/json');
}


    // ─── PRIVATE HELPER ───────────────────────────────────────────────────────

    // Reads the JWT already validated by JwtAuthMiddleware and returns the user
    private function getAuthUser(Request $request): array
{
    $user = $request->getAttribute('user');
    $userId = is_array($user) ? $user['id'] : $user->id;

    $db = \App\Models\Database::connect();
    $stmt = $db->prepare("SELECT id, name, email, role, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(\PDO::FETCH_ASSOC);
}
}