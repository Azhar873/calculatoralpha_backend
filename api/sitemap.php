<?php
/**
 * Public Sitemap API
 * GET /api/sitemap
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

function ensurePublicSitemapTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sitemap (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url_type ENUM('home', 'calculator', 'category', 'page') NOT NULL,
        source_id INT DEFAULT NULL,
        slug VARCHAR(255) DEFAULT NULL,
        url VARCHAR(500) NOT NULL,
        lastmod DATE NOT NULL,
        changefreq VARCHAR(20) NOT NULL DEFAULT 'monthly',
        priority DECIMAL(2,1) NOT NULL DEFAULT 0.8,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_sitemap_source (url_type, source_id),
        KEY idx_sitemap_url (url)
    )");
}

function syncPublicSitemapTable($pdo) {
    $site_url = 'https://calculatoralpha.com';
    $date = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM sitemap');
        $insert = $pdo->prepare('INSERT INTO sitemap (url_type, source_id, slug, url, lastmod, changefreq, priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute(['home', null, '', $site_url . '/', $date, 'weekly', 1.0]);

        $calculators = $pdo->query("SELECT id, slug FROM calculators WHERE no_index = 0 AND is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($calculators as $calculator) {
            $insert->execute(['calculator', $calculator['id'], $calculator['slug'], $site_url . '/' . ltrim($calculator['slug'], '/'), $date, 'monthly', 0.8]);
        }
        foreach ($pdo->query('SELECT id, slug FROM categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) as $category) {
            $insert->execute(['category', $category['id'], $category['slug'], $site_url . '/' . ltrim($category['slug'], '/'), $date, 'weekly', 0.7]);
        }
        foreach ($pdo->query('SELECT id, slug FROM pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) as $page) {
            $insert->execute(['page', $page['id'], $page['slug'], $site_url . '/' . ltrim($page['slug'], '/'), $date, 'monthly', 0.6]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

ensurePublicSitemapTable($pdo);
if ((int) $pdo->query('SELECT COUNT(*) FROM sitemap')->fetchColumn() === 0) {
    syncPublicSitemapTable($pdo);
}

$stmt = $pdo->query('SELECT id, url_type, source_id, slug, url, lastmod, changefreq, priority FROM sitemap ORDER BY id ASC');
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['format'] ?? '') === 'xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($entries as $entry) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($entry['url'], ENT_XML1) . "</loc>\n";
        $xml .= "    <lastmod>" . htmlspecialchars($entry['lastmod'], ENT_XML1) . "</lastmod>\n";
        $xml .= "    <changefreq>" . htmlspecialchars($entry['changefreq'], ENT_XML1) . "</changefreq>\n";
        $xml .= "    <priority>" . htmlspecialchars($entry['priority'], ENT_XML1) . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";
    echo $xml;
    exit;
}

sendResponse($entries);