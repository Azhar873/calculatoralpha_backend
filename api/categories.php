<?php
/**
 * Categories API
 * GET /api/categories
 * GET /api/categories/{slug}
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // If there is a second part in the path (e.g., /api/categories/math)
    if (isset($parts[1])) {
        $slug = $parts[1];
        
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        $category = $stmt->fetch();

        if ($category) {
            // Get calculators for this category
$stmt = $pdo->prepare("SELECT c.*, cat.slug as category, c.is_featured as featured, c.is_featured as popular FROM calculators c JOIN categories cat ON c.category_id = cat.id WHERE c.category_id = ? AND c.is_active = 1 ORDER BY c.sort_order ASC");
            $stmt->execute([$category['id']]);
            $category['calculators'] = $stmt->fetchAll();
            
            sendResponse($category);
        } else {
            sendResponse(['error' => 'Category not found'], 404);
        }
    } else {
        $includeCalculators = isset($_GET['include']) && $_GET['include'] === 'calculators';
        
        // List all categories with calculator count
        $stmt = $pdo->query("SELECT *, (SELECT COUNT(*) FROM calculators WHERE category_id = categories.id) as count FROM categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($includeCalculators) {
            foreach ($categories as &$category) {
                $stmt = $pdo->prepare("SELECT c.id, c.name, c.slug, c.icon, c.description, c.meta, c.language, c.heading, c.meta_title, c.meta_description, c.no_index, c.mathjax, c.is_active, c.sort_order, c.is_featured, c.seo_points, c.created_at, c.updated_at, cat.slug as category_slug, CONCAT('/', c.slug) as path FROM calculators c JOIN categories cat ON c.category_id = cat.id WHERE c.category_id = ? AND c.is_active = 1 ORDER BY c.sort_order ASC");
                $stmt->execute([$category['id']]);
                
                $calculators = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($calculators as &$calc) {
                    $calc['path'] = $calc['path'] ?: '/' . $calc['slug'];
                    $calc['slug'] = $calc['slug'] ?? null;
                }
                $category['calculators'] = $calculators;
                $category['title'] = $category['name'];
                $category['gradient'] = $category['color'] ?: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }
        
        sendResponse($categories);
    }
} else {
    sendResponse(['error' => 'Method not allowed'], 405);
}
