<?php
/**
 * Admin API
 * Handles CRUD for calculators, categories, settings and stats
 * Access restricted to role='admin'
 */

$method = $_SERVER['REQUEST_METHOD'];
$admin_endpoint = $parts[1] ?? ''; // stats, calculators, categories, settings

// --- ADMIN AUTH CHECK ---
$headers = getallheaders();
$token = null;
if (isset($headers['Authorization'])) {
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    sendResponse(['error' => 'Unauthorized - No token'], 401);
}

// Check user role
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE api_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    sendResponse(['error' => 'Unauthorized - Admin access required'], 403);
}
// --- END AUTH CHECK ---

// Ensure sitemap schema exists before any sitemap generation request.
function ensureSitemapTable($pdo) {
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

ensureSitemapTable($pdo);
ensureCalculatorSeoColumns($pdo);

function regenerateSitemapFile($pdo) {
    $site_url = 'https://calculatoralpha.com';
    $date = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM sitemap');
        $insert = $pdo->prepare('INSERT INTO sitemap (url_type, source_id, slug, url, lastmod, changefreq, priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute(['home', null, '', $site_url . '/', $date, 'weekly', 1.0]);

        $calculators = $pdo->query("SELECT id, slug FROM calculators WHERE sitemap_ever_indexed = 1 AND no_index = 0 AND is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($calculators as $calculator) {
            $insert->execute(['calculator', $calculator['id'], $calculator['slug'], $site_url . '/' . ltrim($calculator['slug'], '/'), $date, 'monthly', 0.8]);
        }

        $categories = $pdo->query('SELECT id, slug FROM categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categories as $category) {
            $insert->execute(['category', $category['id'], $category['slug'], $site_url . '/' . ltrim($category['slug'], '/'), $date, 'weekly', 0.7]);
        }

        $pages = $pdo->query('SELECT id, slug FROM pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pages as $page) {
            $insert->execute(['page', $page['id'], $page['slug'], $site_url . '/' . ltrim($page['slug'], '/'), $date, 'monthly', 0.6]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    $stmt = $pdo->query('SELECT url, lastmod, changefreq, priority FROM sitemap ORDER BY id ASC');
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sitemap_url) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($sitemap_url['url'], ENT_XML1) . "</loc>\n    <lastmod>{$sitemap_url['lastmod']}</lastmod>\n    <changefreq>{$sitemap_url['changefreq']}</changefreq>\n    <priority>{$sitemap_url['priority']}</priority>\n  </url>\n";
    }

    $xml .= "</urlset>\n";
    $sitemap_path = getenv('SITEMAP_PATH') ?: 'F:/new-website/calculator-website/public/sitemap.xml';
    if (file_put_contents($sitemap_path, $xml) === false) {
        throw new RuntimeException("Could not write sitemap file: {$sitemap_path}");
    }

    return $sitemap_path;
}

if ((int) $pdo->query('SELECT COUNT(*) FROM sitemap')->fetchColumn() === 0) {
    regenerateSitemapFile($pdo);
}

if ($admin_endpoint === 'regenerate-sitemap' && $method === 'POST') {
    try {
        sendResponse(['message' => 'Sitemap regenerated', 'path' => regenerateSitemapFile($pdo)]);
    } catch (Throwable $error) {
        sendResponse(['error' => $error->getMessage()], 500);
    }
}

function ensurePagesContactColumns($pdo) {
    $columns = [
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

function ensureCategorySeoColumns($pdo) {
    $columns = [
        'meta_title' => 'TEXT DEFAULT NULL',
        'meta_description' => 'TEXT DEFAULT NULL',
        'meta_keywords' => 'TEXT DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN $column $definition");
        }
    }
}

function ensureCalculatorSeoColumns($pdo) {
    $columns = [
        'sitemap_index' => 'BOOLEAN DEFAULT TRUE',
        'sitemap_ever_indexed' => 'BOOLEAN NOT NULL DEFAULT FALSE',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM calculators LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE calculators ADD COLUMN $column $definition");
        }
    }

    // Preserve sitemap inclusion for calculators that were already indexed.
    $pdo->exec("UPDATE calculators SET sitemap_ever_indexed = 1 WHERE sitemap_index = 1");
}

ensurePagesContactColumns($pdo);
ensureCategorySeoColumns($pdo);
ensureCalculatorSeoColumns($pdo);

switch ($admin_endpoint) {
    case 'stats':
        if ($method === 'GET') {
            $total_calculators = $pdo->query("SELECT COUNT(*) FROM calculators")->fetchColumn();
            $index_calculators = $pdo->query("SELECT COUNT(*) FROM calculators WHERE no_index = 0")->fetchColumn();
            $no_index_calculators = $pdo->query("SELECT COUNT(*) FROM calculators WHERE no_index = 1")->fetchColumn();
            $total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

            sendResponse([
                'total_calculators' => (int)$total_calculators,
                'index_calculators' => (int)$index_calculators,
                'no_index_calculators' => (int)$no_index_calculators,
                'total_categories' => (int)$total_categories
            ]);
        }
        break;

    case 'calculators':
        if ($method === 'GET') {
            $id = $parts[2] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM calculators WHERE id = ?");
                $stmt->execute([$id]);
                $calc = $stmt->fetch();
                sendResponse($calc ?: ['error' => 'Not found'], $calc ? 200 : 404);
            } else {
                $stmt = $pdo->query("SELECT c.*, cat.name as category_name FROM calculators c JOIN categories cat ON c.category_id = cat.id ORDER BY c.id DESC");
                sendResponse($stmt->fetchAll());
            }
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
                $sitemap_index = !empty($input['sitemap_index']) ? 1 : 0;
                $sql = "INSERT INTO calculators (category_id, name, slug, icon, description, language, heading, meta_title, meta_description, no_index, mathjax, sitemap_index, sitemap_ever_indexed, is_featured, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['category_id'], $input['name'], $input['slug'], $input['icon'] ?? '', $input['description'] ?? '',
                $input['language'] ?? 'English', $input['heading'] ?? '', $input['meta_title'] ?? '', $input['meta_description'] ?? '',
                $input['no_index'] ?? 0, $input['mathjax'] ?? 0, $sitemap_index, $sitemap_index, $input['is_featured'] ?? 0, $input['is_active'] ?? 1
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['id' => $pdo->lastInsertId(), 'message' => 'Calculator created'], 201);
        } elseif ($method === 'PUT') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $input = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("SELECT * FROM calculators WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) sendResponse(['error' => 'Calculator not found'], 404);

            $requested_sitemap_index = isset($input['sitemap_index'])
                ? (!empty($input['sitemap_index']) ? 1 : 0)
                : (int) ($existing['sitemap_index'] ?? 1);
            $sitemap_ever_indexed = ((int) ($existing['sitemap_ever_indexed'] ?? 0) === 1 || $requested_sitemap_index === 1) ? 1 : 0;
            $sql = "UPDATE calculators SET category_id=?, name=?, slug=?, icon=?, description=?, language=?, heading=?, meta_title=?, meta_description=?, no_index=?, mathjax=?, sitemap_index=?, sitemap_ever_indexed=?, is_featured=?, is_active=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['category_id'] ?? $existing['category_id'],
                $input['name'] ?? $existing['name'],
                $input['slug'] ?? $existing['slug'],
                $input['icon'] ?? $existing['icon'],
                $input['description'] ?? $existing['description'],
                $input['language'] ?? $existing['language'],
                $input['heading'] ?? $existing['heading'],
                $input['meta_title'] ?? $existing['meta_title'],
                $input['meta_description'] ?? $existing['meta_description'],
                isset($input['no_index']) ? $input['no_index'] : $existing['no_index'],
                isset($input['mathjax']) ? $input['mathjax'] : $existing['mathjax'],
                $requested_sitemap_index,
                $sitemap_ever_indexed,
                isset($input['is_featured']) ? $input['is_featured'] : $existing['is_featured'],
                isset($input['is_active']) ? $input['is_active'] : $existing['is_active'],
                $id
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Calculator updated']);
        } elseif ($method === 'DELETE') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $stmt = $pdo->prepare("DELETE FROM calculators WHERE id = ?");
            $stmt->execute([$id]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Calculator deleted']);
        }
        break;

    case 'categories':
        if ($method === 'GET') {
            $id = $parts[2] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT categories.*, (SELECT COUNT(*) FROM calculators WHERE category_id = categories.id) AS calculator_count FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $cat = $stmt->fetch();
                sendResponse($cat ?: ['error' => 'Not found'], $cat ? 200 : 404);
            } else {
                $stmt = $pdo->query("SELECT categories.*, (SELECT COUNT(*) FROM calculators WHERE category_id = categories.id) AS calculator_count FROM categories ORDER BY id DESC");
                sendResponse($stmt->fetchAll());
            }
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color, description, meta_title, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['name'],
                $input['slug'],
                $input['icon'] ?? '',
                $input['color'] ?? '',
                $input['description'] ?? '',
                $input['meta_title'] ?? '',
                $input['meta_description'] ?? '',
                $input['meta_keywords'] ?? '',
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['id' => $pdo->lastInsertId(), 'message' => 'Category created'], 201);
        } elseif ($method === 'PUT') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, icon=?, color=?, description=?, meta_title=?, meta_description=?, meta_keywords=? WHERE id=?");
            $stmt->execute([
                $input['name'] ?? '',
                $input['slug'] ?? '',
                $input['icon'] ?? '',
                $input['color'] ?? '',
                $input['description'] ?? '',
                $input['meta_title'] ?? '',
                $input['meta_description'] ?? '',
                $input['meta_keywords'] ?? '',
                $id,
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Category updated']);
        } elseif ($method === 'DELETE') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Category deleted']);
        }
        break;

    case 'pages':
        if ($method === 'GET') {
            $id = $parts[2] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
                $stmt->execute([$id]);
                $page = $stmt->fetch();
                sendResponse($page ?: ['error' => 'Not found'], $page ? 200 : 404);
            } else {
                $stmt = $pdo->query("SELECT * FROM pages ORDER BY id DESC");
                sendResponse($stmt->fetchAll());
            }
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare(
                "INSERT INTO pages (title, slug, heading, banner_image, description, meta_title, meta_description, show_in_header, footer_text, footer_section, page_type, contact_email, contact_phone, contact_address, contact_intro, contact_form_title, contact_submit_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $input['title'] ?? '',
                $input['slug'] ?? '',
                $input['heading'] ?? '',
                $input['banner_image'] ?? '',
                $input['description'] ?? '',
                $input['meta_title'] ?? '',
                $input['meta_description'] ?? '',
                !empty($input['show_in_header']) ? 1 : 0,
                $input['footer_text'] ?? '',
                $input['footer_section'] ?? '',
                $input['page_type'] ?? 'default',
                $input['contact_email'] ?? '',
                $input['contact_phone'] ?? '',
                $input['contact_address'] ?? '',
                $input['contact_intro'] ?? '',
                $input['contact_form_title'] ?? '',
                $input['contact_submit_label'] ?? '',
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['id' => $pdo->lastInsertId(), 'message' => 'Page created'], 201);
        } elseif ($method === 'PUT') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) sendResponse(['error' => 'Page not found'], 404);

            $stmt = $pdo->prepare(
                "UPDATE pages SET title=?, slug=?, heading=?, banner_image=?, description=?, meta_title=?, meta_description=?, show_in_header=?, footer_text=?, footer_section=?, page_type=?, contact_email=?, contact_phone=?, contact_address=?, contact_intro=?, contact_form_title=?, contact_submit_label=? WHERE id=?"
            );
            $stmt->execute([
                $input['title'] ?? $existing['title'],
                $input['slug'] ?? $existing['slug'],
                $input['heading'] ?? $existing['heading'],
                $input['banner_image'] ?? $existing['banner_image'],
                $input['description'] ?? $existing['description'],
                $input['meta_title'] ?? $existing['meta_title'],
                $input['meta_description'] ?? $existing['meta_description'],
                array_key_exists('show_in_header', $input)
                    ? (!empty($input['show_in_header']) ? 1 : 0)
                    : $existing['show_in_header'],
                $input['footer_text'] ?? $existing['footer_text'],
                $input['footer_section'] ?? $existing['footer_section'],
                $input['page_type'] ?? $existing['page_type'],
                $input['contact_email'] ?? $existing['contact_email'],
                $input['contact_phone'] ?? $existing['contact_phone'],
                $input['contact_address'] ?? $existing['contact_address'],
                $input['contact_intro'] ?? $existing['contact_intro'],
                $input['contact_form_title'] ?? $existing['contact_form_title'],
                $input['contact_submit_label'] ?? $existing['contact_submit_label'],
                $id,
            ]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Page updated']);
        } elseif ($method === 'DELETE') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$id]);
            regenerateSitemapFile($pdo);
            sendResponse(['message' => 'Page deleted']);
        }
        break;

    case 'settings':
        if ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'home_sections'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            sendResponse(json_decode($val, true));
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $jsonValue = json_encode($input);
            $stmt = $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES ('home_sections', ?) " .
                "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$jsonValue]);
            sendResponse(['message' => 'Settings updated']);
        }
        break;

    case 'contacts':
        // Admin: list contact inquiries or fetch/delete a specific inquiry
        if ($method === 'GET') {
            $id = $parts[2] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM contact_inquiries WHERE id = ?");
                $stmt->execute([$id]);
                $inq = $stmt->fetch();
                sendResponse($inq ?: ['error' => 'Not found'], $inq ? 200 : 404);
            } else {
                $stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC");
                $inquiries = $stmt->fetchAll();
                $total = count($inquiries);
                sendResponse(['count' => $total, 'data' => $inquiries]);
            }
        } elseif ($method === 'DELETE') {
            $id = $parts[2] ?? null;
            if (!$id) sendResponse(['error' => 'ID required'], 400);
            $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(['message' => 'Inquiry deleted']);
        }
        break;

    default:
        sendResponse(['error' => 'Admin endpoint not found', 'admin_endpoint' => $admin_endpoint, 'parts' => $parts], 404);
        break;
}
