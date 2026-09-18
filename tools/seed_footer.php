<?php
/**
 * Seed footer defaults into settings.home_sections
 * Run: php tools/seed_footer.php
 */

require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'home_sections'");
    $stmt->execute();
    $val = $stmt->fetchColumn();

    $stored = $val ? json_decode($val, true) : [];
    if (!is_array($stored)) $stored = [];

    $defaults = [
        'section1' => $stored['section1'] ?? '',
        'section2' => $stored['section2'] ?? '',
        'section3' => $stored['section3'] ?? '',
        'section4' => $stored['section4'] ?? '',
        'section5' => $stored['section5'] ?? '',
        'section6' => $stored['section6'] ?? '',
        'section7' => $stored['section7'] ?? '',
        'section8' => $stored['section8'] ?? '',
        'footerText' => $stored['footerText'] ?? 'Smart calculators and easy math tools for daily life, finance, health and study. Everything you need in one clean, modern hub.',
        'footerCopyright' => $stored['footerCopyright'] ?? ('© ' . date('Y') . ' Calculatoralpha. All rights reserved.')
    ];

    $jsonValue = json_encode($defaults, JSON_UNESCAPED_UNICODE);

    $upd = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('home_sections', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $upd->execute([$jsonValue]);

    echo "Footer defaults seeded successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}