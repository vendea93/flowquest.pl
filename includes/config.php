<?php
/**
 * FlowQuest - Konfiguracja
 */

// Ścieżki
define('BASE_DIR', dirname(__DIR__));
define('INCLUDES_DIR', BASE_DIR . '/includes');
define('ADMIN_DIR', BASE_DIR . '/admin');
define('HELPERS_DIR', BASE_DIR . '/helpers');
define('DATA_DIR', BASE_DIR . '/data');
define('CACHE_DIR', BASE_DIR . '/cache');

// Baza danych
define('DB_FILE', DATA_DIR . '/flowquest.db');
define('DB_BACKUP_DIR', DATA_DIR . '/backups');

// CEMR Integration
define('CEMR_BASE_URL', 'https://cemr.flowquest.pl');
define('CEMR_DEMO_URL', CEMR_BASE_URL . '/demo');
define('CEMR_REGISTER_URL', CEMR_BASE_URL . '/rejestracja');
define('CEMR_CONTACT_URL', CEMR_BASE_URL . '/kontakt');

// Strona
define('SITE_NAME', 'FlowQuest');
define('SITE_DESCRIPTION', 'System łączący sprzedaż, realizację i zarządzanie w jednym miejscu');
define('DEFAULT_LOCALE', 'pl');
define('SUPPORTED_LOCALES', ['pl']);

// SEO domyślne
define('DEFAULT_SEO_TITLE', 'FlowQuest | System dla firmy łączący sprzedaż, realizację i zarządzanie');
define('DEFAULT_SEO_DESCRIPTION', 'FlowQuest porządkuje sposób działania firmy. Łączy CRM, projekty, zadania i finanse w jednym systemie wdrażanym etapowo.');
define('DEFAULT_SEO_KEYWORDS', 'FlowQuest, CRM, wdrożenie CRM, automatyzacja procesów, system dla firmy, zarządzanie projektami, sprzedaż i realizacja');

// Cache
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 godzina
define('CACHE_CLEANUP_PROBABILITY', 10); // 10% szans na czyszczenie starych cache

// Bezpieczeństwo
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$snNXa9SpsfiTAbyHiDB63.hVyxA3gK9WqHgn94jEcu6rX2Pj8a/52'); // admin123
define('SESSION_TIMEOUT', 7200); // 2 godziny

// Formularze
define('CONTACT_EMAIL', 'kontakt@flowquest.pl');
define('FORM_SUBMISSION_LOG', true);
define('FORM_REDIRECT_DELAY', 0); // natychmiastowe przekierowanie do CEMR

// Debug
define('DEBUG_MODE', false);
define('ERROR_LOG', DATA_DIR . '/error.log');

// Ustawienia czasu
date_default_timezone_set('Europe/Warsaw');

// Sprawdź czy katalogi istnieją
$requiredDirs = [DATA_DIR, CACHE_DIR, DB_BACKUP_DIR];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Error handling
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG);
}

// Start sesji
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
    
    // Sprawdź timeout sesji
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}