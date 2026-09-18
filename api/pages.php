<?php
/**
 * Public Pages API
 * GET /api/pages/:slug
 */

$method = $_SERVER['REQUEST_METHOD'];
$page_endpoint = $parts[1] ?? '';

function ensurePagesColumns($pdo) {
    $columns = [
        'footer_section' => "VARCHAR(50) DEFAULT NULL",
        'show_in_header' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'page_type' => "VARCHAR(50) DEFAULT 'default'",
        'contact_email' => 'VARCHAR(255) DEFAULT NULL',
        'contact_phone' => 'VARCHAR(100) DEFAULT NULL',
        'contact_address' => 'TEXT DEFAULT NULL',
        'contact_intro' => 'TEXT DEFAULT NULL',
        'contact_form_title' => 'VARCHAR(255) DEFAULT NULL',
        'contact_submit_label' => 'VARCHAR(100) DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM pages LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE pages ADD COLUMN $column $definition");
        }
    }
}

ensurePagesColumns($pdo);

if ($method !== 'GET') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

$slug = $parts[1] ?? null;

// If no slug provided, return list of all pages (public listing)
if (!$slug) {
    $stmt = $pdo->query("SELECT id, title, slug, heading, banner_image, show_in_header, footer_section, page_type FROM pages ORDER BY id DESC");
    $pages = $stmt->fetchAll();
    sendResponse($pages);
}

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    sendResponse(['error' => 'Page not found'], 404);
}

sendResponse($page);
