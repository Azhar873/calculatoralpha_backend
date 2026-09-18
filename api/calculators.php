<?php
/**
 * Calculators API
 * GET /api/calculators
 * GET /api/calculators/{cat_slug}/{calc_slug}
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Single calculator detail: /api/calculators/math/matrix-calculator
    if (isset($parts[1]) && isset($parts[2])) {
        $cat_slug = $parts[1];
        $calc_slug = $parts[2];

        $stmt = $pdo->prepare("
            SELECT c.id, c.category_id, c.name, c.slug, c.icon, c.description, c.meta, c.language, c.heading, c.meta_title, c.meta_description, c.no_index, c.mathjax, c.sitemap_index, c.sort_order, c.is_featured, c.seo_points, c.created_at, c.updated_at, cat.name as category_name, cat.slug as category, cat.slug as category_slug, c.is_featured as featured, c.is_featured as popular, CONCAT('/', c.slug) as path FROM calculators c 
            JOIN categories cat ON c.category_id = cat.id 
            WHERE cat.slug = ? AND c.slug = ? AND c.is_active = 1
        ");
        $stmt->execute([$cat_slug, $calc_slug]);
        $calculator = $stmt->fetch();

        if ($calculator) {
            $calculator['meta'] = json_decode($calculator['meta'] ?? '{}', true);
            sendResponse($calculator);
        } else {
            sendResponse(['error' => 'Calculator not found'], 404);
        }
    } else {
        // List calculators with optional filters
        $search = $_GET['search'] ?? null;
        $featured = $_GET['featured'] ?? null;

        $sql = "SELECT c.id, c.category_id, c.name, c.slug, c.icon, c.description, c.meta, c.language, c.heading, c.meta_title, c.meta_description, c.no_index, c.mathjax, c.sitemap_index, c.sort_order, c.is_featured, c.seo_points, c.created_at, c.updated_at, cat.name as category_name, cat.slug as category, cat.slug as category_slug, c.is_featured as featured, c.is_featured as popular, CONCAT('/', c.slug) as path FROM calculators c JOIN categories cat ON c.category_id = cat.id WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($featured !== null) {
            $sql .= " AND c.is_featured = ?";
            $params[] = $featured;
        }

        $sql .= " AND c.is_active = 1";
        $sql .= " ORDER BY c.sort_order ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $calculators = $stmt->fetchAll();

        // Decode JSON meta for each
        foreach ($calculators as &$calc) {
            $calc['meta'] = json_decode($calc['meta'] ?? '{}', true);
        }

        sendResponse($calculators);
    }
} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
