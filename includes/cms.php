<?php
/**
 * FlowQuest - Minimalny CMS z integracją CEMR
 * 
 * SYSTEM ZAŁOŻENIA:
 * 1. Prosta baza SQLite (brak MySQL dla uproszczenia)
 * 2. Panel admina tylko do zarządzania treściami
 * 3. Wszystkie formularze przekierowują do CEMR
 * 4. SEO artykuły dla 8 branż
 * 5. Cache dla wydajności
 */

// ============================================
// KONFIGURACJA
// ============================================

define('BASE_PATH', dirname(__DIR__));
define('DB_PATH', BASE_PATH . '/data/flowquest.db');
define('CACHE_PATH', BASE_PATH . '/data/cache');
define('CEMR_BASE_URL', 'https://cemr.flowquest.pl');
define('CEMR_DEMO_URL', CEMR_BASE_URL . '/demo');
define('CEMR_REGISTER_URL', CEMR_BASE_URL . '/rejestracja');

// Ustawienia czasu
date_default_timezone_set('Europe/Warsaw');

// ============================================
// FUNKCJE BAZY DANYCH
// ============================================

function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Utwórz katalog data jeśli nie istnieje
            if (!is_dir(dirname(DB_PATH))) {
                mkdir(dirname(DB_PATH), 0755, true);
            }
            
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA journal_mode = WAL');
            
            // Utwórz tabele jeśli nie istnieją
            initDatabase($db);
            
        } catch (PDOException $e) {
            error_log('Błąd bazy danych: ' . $e->getMessage());
            die('Błąd systemu. Proszę spróbować później.');
        }
    }
    
    return $db;
}

function initDatabase($db) {
    // Sprawdź czy tabele istnieją
    $tables = [
        'categories', 'articles', 'settings', 'page_contents', 
        'form_submissions', 'menu_items'
    ];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$stmt->fetch()) {
            // Tabela nie istnieje - utwórz strukturę
            createDatabaseStructure($db);
            seedInitialData($db);
            break;
        }
    }
}

function createDatabaseStructure($db) {
    $sql = <<<SQL
    -- Kategorie branżowe
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        name_pl TEXT NOT NULL,
        name_en TEXT,
        description TEXT,
        sort_order INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Artykuły bloga
    CREATE TABLE articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        category_id INTEGER,
        status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
        
        -- Treści
        title_pl TEXT NOT NULL,
        title_en TEXT,
        excerpt_pl TEXT,
        excerpt_en TEXT,
        content_pl TEXT NOT NULL,
        content_en TEXT,
        
        -- SEO
        meta_title TEXT,
        meta_description TEXT,
        meta_keywords TEXT,
        focus_keyword TEXT,
        
        -- Media
        featured_image TEXT,
        gallery_images TEXT,
        
        -- Dane techniczne
        read_time TEXT,
        author TEXT DEFAULT 'FlowQuest Team',
        views_count INTEGER DEFAULT 0,
        
        -- Daty
        published_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    );
    
    -- Ustawienia strony
    CREATE TABLE settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        type TEXT DEFAULT 'text',
        category TEXT DEFAULT 'general',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Treści stron (do edycji w adminie)
    CREATE TABLE page_contents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_slug TEXT NOT NULL,
        content_key TEXT NOT NULL,
        locale TEXT DEFAULT 'pl',
        content_type TEXT DEFAULT 'text',
        content_value TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        UNIQUE(page_slug, content_key, locale)
    );
    
    -- Log formularzy (tylko log, przekierowanie do CEMR)
    CREATE TABLE form_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_type TEXT NOT NULL,
        full_name TEXT,
        email TEXT NOT NULL,
        phone TEXT,
        company TEXT,
        message TEXT,
        source_url TEXT,
        ip_address TEXT,
        user_agent TEXT,
        status TEXT DEFAULT 'new',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Menu
    CREATE TABLE menu_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER,
        label_pl TEXT NOT NULL,
        label_en TEXT,
        url TEXT NOT NULL,
        target TEXT DEFAULT '_self',
        icon TEXT,
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
    );
    
    -- Indeksy dla wydajności
    CREATE INDEX idx_articles_status ON articles(status);
    CREATE INDEX idx_articles_published ON articles(published_at);
    CREATE INDEX idx_articles_category ON articles(category_id);
    CREATE INDEX idx_form_status ON form_submissions(status);
SQL;
    
    $db->exec($sql);
}

function seedInitialData($db) {
    // Kategorie branżowe
    $categories = [
        ['it-software', 'IT & Software Development', 'System dla agencji IT i software houseów', 1],
        ['marketing-agencies', 'Marketing & Agencje', 'Rozwiązania dla agencji marketingowych', 2],
        ['production-manufacturing', 'Produkcja & Manufacturing', 'System dla firm produkcyjnych', 3],
        ['b2b-services', 'Usługi B2B & Konsulting', 'Dla firm usługowych B2B i konsultingowych', 4],
        ['ecommerce', 'E-commerce', 'Rozwiązania dla sklepów internetowych', 5],
        ['education-training', 'Edukacja & Szkolenia', 'Dla firm szkoleniowych i edukacyjnych', 6],
        ['finance-accounting', 'Finanse & Księgowość', 'System dla biur rachunkowych i finansowych', 7],
        ['creative-design', 'Creative & Design', 'Dla agencji kreatywnych i studiów designu', 8]
    ];
    
    $stmt = $db->prepare('INSERT INTO categories (slug, name_pl, description, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    
    // Domyślne ustawienia
    $settings = [
        ['site_name', 'FlowQuest', 'text', 'general'],
        ['site_description', 'System łączący sprzedaż, realizację i zarządzanie w jednym miejscu', 'text', 'general'],
        ['contact_email', 'kontakt@flowquest.pl', 'text', 'contact'],
        ['demo_form_url', CEMR_DEMO_URL, 'text', 'forms'],
        ['registration_url', CEMR_REGISTER_URL, 'text', 'forms'],
        ['cemr_instance_url', CEMR_BASE_URL, 'text', 'integration'],
        ['seo_default_title', 'FlowQuest | System dla firmy łączący sprzedaż, realizację i zarządzanie', 'text', 'seo'],
        ['seo_default_description', 'FlowQuest porządkuje sposób działania firmy. Łączy CRM, projekty, zadania i finanse w jednym systemie.', 'text', 'seo']
    ];
    
    $stmt = $db->prepare('INSERT INTO settings (key, value, type, category) VALUES (?, ?, ?, ?)');
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    
    // Menu główne
    $menuItems = [
        [null, 'Strona główna', 'index.html', '_self', null, 0, 1, 1],
        [null, 'O FlowQuest', '#', '_self', null, 0, 1, 2],
        [null, 'Dla kogo?', '#', '_self', null, 0, 1, 3],
        [null, 'Jak to działa?', '#', '_self', null, 0, 1, 4],
        [null, 'Blog', 'blog.html', '_self', null, 0, 1, 5],
        [null, 'Kontakt', 'contact.html', '_self', null, 0, 1, 6],
        [null, 'Umów demo', CEMR_DEMO_URL . '?source=header', '_self', null, 0, 1, 7]
    ];
    
    $stmt = $db->prepare('INSERT INTO menu_items (parent_id, label_pl, url, target, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($menuItems as $item) {
        $stmt->execute($item);
    }
    
    // Pobierz ID menu
    $parentIds = [];
    $stmt = $db->query('SELECT id, label_pl FROM menu_items WHERE parent_id IS NULL ORDER BY sort_order');
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($parents as $parent) {
        $parentIds[$parent['label_pl']] = $parent['id'];
    }
    
    // Podmenu "O FlowQuest"
    if (isset($parentIds['O FlowQuest'])) {
        $submenu = [
            [$parentIds['O FlowQuest'], 'O nas', 'about.html', '_self', null, 1, 1],
            [$parentIds['O FlowQuest'], 'Zespół', 'team.html', '_self', null, 2, 1],
            [$parentIds['O FlowQuest'], 'Case studies', '#case-studies', '_self', null, 3, 1]
        ];
        
        foreach ($submenu as $item) {
            $stmt->execute($item);
        }
    }
    
    // Podmenu "Dla kogo?" (branże)
    if (isset($parentIds['Dla kogo?'])) {
        $submenu = [
            [$parentIds['Dla kogo?'], 'IT & Software', 'blog.html?category=it-software', '_self', null, 1, 1],
            [$parentIds['Dla kogo?'], 'Marketing & Agencje', 'blog.html?category=marketing-agencies', '_self', null, 2, 1],
            [$parentIds['Dla kogo?'], 'Produkcja', 'blog.html?category=production-manufacturing', '_self', null, 3, 1],
            [$parentIds['Dla kogo?'], 'Usługi B2B', 'blog.html?category=b2b-services', '_self', null, 4, 1],
            [$parentIds['Dla kogo?'], 'E-commerce', 'blog.html?category=ecommerce', '_self', null, 5, 1],
            [$parentIds['Dla kogo?'], 'Edukacja & Szkolenia', 'blog.html?category=education-training', '_self', null, 6, 1],
            [$parentIds['Dla kogo?'], 'Finanse & Księgowość', 'blog.html?category=finance-accounting', '_self', null, 7, 1],
            [$parentIds['Dla kogo?'], 'Creative & Design', 'blog.html?category=creative-design', '_self', null, 8, 1]
        ];
        
        foreach ($submenu as $item) {
            $stmt->execute($item);
        }
    }
}

// ============================================
// FUNKCJE CMS
// ============================================

function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $db = getDB();
        $stmt = $db->query('SELECT key, value FROM settings');
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings[$key] ?? $default;
}

function getMenu($parentId = null) {
    $db = getDB();
    
    $sql = 'SELECT * FROM menu_items WHERE is_active = 1';
    $params = [];
    
    if ($parentId === null) {
        $sql .= ' AND parent_id IS NULL';
    } else {
        $sql .= ' AND parent_id = ?';
        $params[] = $parentId;
    }
    
    $sql .= ' ORDER BY sort_order';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategories() {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM categories ORDER BY sort_order');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArticles($limit = 10, $category = null, $status = 'published') {
    $db = getDB();
    
    $sql = 'SELECT a.*, c.name_pl as category_name 
            FROM articles a 
            LEFT JOIN categories c ON a.category_id = c.id 
            WHERE a.status = ?';
    
    $params = [$status];
    
    if ($category) {
        $sql .= ' AND (c.slug = ? OR c.id = ?)';
        $params[] = $category;
        $params[] = $category;
    }
    
    $sql .= ' ORDER BY a.published_at DESC LIMIT ?';
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArticleBySlug($slug) {
    $db = getDB();
    
    // Zwiększ licznik wyświetleń
    $db->exec('UPDATE articles SET views_count = views_count + 1 WHERE slug = ' . $db->quote($slug));
    
    $stmt = $db->prepare('SELECT a.*, c.name_pl as category_name 
                         FROM articles a 
                         LEFT JOIN categories c ON a.category_id = c.id 
                         WHERE a.slug = ? AND a.status = "published"');
    $stmt->execute([$slug]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPageContent($pageSlug, $locale = 'pl') {
    $db = getDB();
    
    $stmt = $db->prepare('SELECT content_key, content_value 
                         FROM page_contents 
                         WHERE page_slug = ? AND locale = ? 
                         ORDER BY sort_order');
    $stmt->execute([$pageSlug, $locale]);
    
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[$row['content_key']] = $row['content_value'];
    }
    
    return $result;
}

// ============================================
// FUNKCJE FORMULARZY (integracja z CEMR)
// ============================================

function handleContactForm($data) {
    $db = getDB();
    
    // Zapisz log w bazie
    $stmt = $db->prepare('INSERT INTO form_submissions 
                         (form_type, full_name, email, phone, company, message, source_url, ip_address, user_agent) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $stmt->execute([
        'contact',
        $data['name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['company'] ?? '',
        $data['message'] ?? '',
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // Przekieruj do CEMR z danymi w URL (lub POST)
    $cemrUrl = CEMR_BASE_URL . '/kontakt?' . http_build_query([
        'name' => $data['name'] ?? '',
        'email' => $data['email'] ?? '',
        'phone' => $data['phone'] ?? '',
        'company' => $data['company'] ?? '',
        'message' => $data['message'] ?? '',
        'source' => 'flowquest_website'
    ]);
    
    header('Location: ' . $cemrUrl);
    exit;
}

function handleDemoForm($data) {
    $db = getDB();
    
    // Zapisz log
    $stmt = $db->prepare('INSERT INTO form_submissions 
                         (form_type, full_name, email, phone, company, message, source_url, ip_address, user_agent) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $stmt->execute([
        'demo',
        $data['name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['company'] ?? '',
        $data['message'] ?? '',
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // Przekieruj do demo w CEMR
    $cemrUrl = CEMR_DEMO_URL . '?' . http_build_query([
        'name' => $data['name'] ?? '',
        'email' => $data['email'] ?? '',
        'phone' => $data['phone'] ?? '',
        'company' => $data['company'] ?? '',
        'source' => 'flowquest_website'
    ]);
    
    header('Location: ' . $cemrUrl);
    exit;
}

function handleNewsletterForm($email) {
    $db = getDB();
    
    // Zapisz log
    $stmt = $db->prepare('INSERT INTO form_submissions 
                         (form_type, email, source_url, ip_address, user_agent) 
                         VALUES (?, ?, ?, ?, ?)');
    
    $stmt->execute([
        'newsletter',
        $email,
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // Przekieruj do rejestracji w CEMR
    $cemrUrl = CEMR_REGISTER_URL . '?' . http_build_query([
        'email' => $email,
        'source' => 'flowquest_newsletter'
    ]);
    
    header('Location: ' . $cemrUrl);
    exit;
}

// ============================================
// FUNKCJE POMOCNICZE
// ============================================

function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function getCurrentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
           "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

function generateSlug($string) {
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = strtolower(trim($string));
    $string = preg_replace('/\s+/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return $string;
}

function getCemrUrl($type = 'demo', $params = []) {
    $baseUrls = [
        'demo' => CEMR_DEMO_URL,
        'register' => CEMR_REGISTER_URL,
        'contact' => CEMR_BASE_URL . '/kontakt'
    ];
    
    $url = $baseUrls[$type] ?? CEMR_DEMO_URL;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

// ============================================
// CACHE SYSTEM (opcjonalny, dla wydajności)
// ============================================

function getCache($key, $ttl = 3600) {
    if (!is_dir(CACHE_PATH)) {
        mkdir(CACHE_PATH, 0755, true);
    }
    
    $cacheFile = CACHE_PATH . '/' . md5($key) . '.cache';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return unserialize(file_get_contents($cacheFile));
    }
    
    return false;
}

function setCache($key, $data) {
    if (!is_dir(CACHE_PATH)) {
        mkdir(CACHE_PATH, 0755, true);
    }
    
    $cacheFile = CACHE_PATH . '/' . md5($key) . '.cache';
    file_put_contents($cacheFile, serialize($data));
}

function clearCache($pattern = '*') {
    $files = glob(CACHE_PATH . '/' . $pattern . '.cache');
    foreach ($files as $file) {
        unlink($file);
    }
}

// ============================================
// AUTOLOADER dla helperów
// ============================================

// Funkcja do ładowania helperów z folderu helpers/
spl_autoload_register(function ($className) {
    $helperFile = BASE_PATH . '/helpers/' . $className . '.php';
    if (file_exists($helperFile)) {
        require_once $helperFile;
    }
});

// ============================================
// START SESJI i inicjalizacja
// ============================================

session_start();

// Automatyczne obsłużenie formularzy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type'])) {
        switch ($_POST['form_type']) {
            case 'contact':
                handleContactForm($_POST);
                break;
            case 'demo':
                handleDemoForm($_POST);
                break;
            case 'newsletter':
                if (!empty($_POST['email'])) {
                    handleNewsletterForm($_POST['email']);
                }
                break;
        }
    }
}

// Inicjalizuj bazę przy pierwszym uruchomieniu
getDB();