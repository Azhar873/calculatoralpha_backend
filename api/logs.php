<?php
/**
 * Logs API
 * POST /api/search-logs
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $results_count = $input['results_count'] ?? 0;
    
    // Optional: Get user from token if provided
    $user_id = null;
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
            $stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            if ($user) $user_id = $user['id'];
        }
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO search_logs (query, results_count, user_id, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$query, $results_count, $user_id, $ip_address]);

    sendResponse(['message' => 'Search logged successfully']);
} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
