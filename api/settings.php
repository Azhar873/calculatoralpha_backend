<?php
/**
 * Public Settings API
 * GET /api/settings
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'home_sections'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    
    // decode stored settings and merge with defaults so missing keys get safe values
    $stored = json_decode($val, true) ?: [];
    $default = [
        'section1' => '',
        'section2' => '',
        'section3' => '',
        'section4' => '',
        'section5' => '',
        'section6' => '',
        'section7' => '',
        'section8' => '',
        // footer defaults
        'footerText' => '',
        'footerCopyright' => ''
    ];

    // Merge stored over defaults so existing values are preserved
    $merged = array_merge($default, $stored);
    sendResponse($merged);
} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}