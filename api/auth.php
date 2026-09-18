<?php
/**
 * Auth API
 * POST /api/login
 * POST /api/register
 * POST /api/logout
 * GET /api/me
 */

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $parts[0]; // login, register, logout, me

// Helper to get Bearer token from header
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

if ($method === 'GET' && $endpoint === 'create-admin') {
    $name = trim($_GET['name'] ?? '');
    $email = trim($_GET['email'] ?? '');
    $password = $_GET['password'] ?? '';

    if (!$name || !$email || !$password) {
        sendResponse(['error' => 'Missing fields'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse(['error' => 'User already exists'], 409);
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, api_token, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$name, $email, $hashed_password, $token]);

        sendResponse([
            'message' => 'Admin user created successfully',
            'access_token' => $token,
            'user' => ['name' => $name, 'email' => $email, 'role' => 'admin']
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Admin creation failed: ' . $e->getMessage()], 400);
    }

} elseif ($method === 'GET' && $endpoint === 'create-user') {
    $name = trim($_GET['name'] ?? '');
    $email = trim($_GET['email'] ?? '');
    $password = $_GET['password'] ?? '';

    if (!$name || !$email || !$password) {
        sendResponse(['error' => 'Missing fields'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse(['error' => 'User already exists'], 409);
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, api_token, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->execute([$name, $email, $hashed_password, $token]);

        sendResponse([
            'message' => 'User created successfully',
            'access_token' => $token,
            'user' => ['name' => $name, 'email' => $email, 'role' => 'user']
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'User creation failed: ' . $e->getMessage()], 400);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($endpoint === 'register') {
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (!$name || !$email || !$password) {
            sendResponse(['error' => 'Missing fields'], 400);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, api_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $token]);
            
            $user_id = $pdo->lastInsertId();
            sendResponse([
                'access_token' => $token,
                'user' => ['id' => $user_id, 'name' => $name, 'email' => $email, 'role' => 'user']
            ], 201);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Registration failed: ' . $e->getMessage()], 400);
        }

    } elseif ($endpoint === 'login') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);

            sendResponse([
                'access_token' => $token,
                'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']]
            ]);
        } else {
            sendResponse(['error' => 'Invalid credentials'], 401);
        }

    } elseif ($endpoint === 'logout') {
        $token = getBearerToken();
        if ($token) {
            $stmt = $pdo->prepare("UPDATE users SET api_token = NULL WHERE api_token = ?");
            $stmt->execute([$token]);
        }
        sendResponse(['message' => 'Logged out']);
    }

} elseif ($method === 'GET' && $endpoint === 'me') {
    $token = getBearerToken();
    if (!$token) {
        sendResponse(['error' => 'Unauthorized'], 401);
    }

    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        sendResponse($user);
    } else {
        sendResponse(['error' => 'Unauthorized'], 401);
    }

} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}