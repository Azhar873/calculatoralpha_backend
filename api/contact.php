<?php
/**
 * Contact API
 * POST /api/contact
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';
    $subject = $input['subject'] ?? '';
    $message = $input['message'] ?? '';

    if (!$name || !$email || !$message) {
        sendResponse(['error' => 'Missing fields'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO contact_inquiries (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message]);

    sendResponse(['message' => 'Your message has been sent successfully!']);
} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
