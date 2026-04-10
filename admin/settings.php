<?php
require_once '../includes/config.php';
require_once '../includes/cms.php';

// Sprawdź autoryzację
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$message = '';

// Obsługa zapisu ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8); // Usuń "setting_" z początku
                $value = trim($value);
                
                // Sprawdź czy ustawienie istnieje
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM settings WHERE key = ?');
                $stmt->execute([$settingKey]);
                $exists = $stmt->fetch()['count'] > 0;
                
                if ($exists) {
                    $stmt = $db->prepare('UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?');
                    $stmt->execute([$value, $settingKey]);
                } else {
                    $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
                    $stmt->execute([$settingKey, $value]);
                }
            }
        }
        
        $message = 'Ustawienia zostały zapisane.';
        
    } elseif (isset($_POST['clear_cache'])) {
        clearCache();
        $message = 'Cache został wyczyszczony.';
        
    } elseif (isset($_POST['backup_db'])) {
        // Utwórz backup bazy danych
        $backupFile = DATA_DIR . '/backups/flowquest_backup_' . date('Y-m-d_H-i-s') . '.db';
        copy(DB_FILE, $backupFile);
        $message = 'Backup bazy danych utworzony: ' . basename($backupFile);
        
    } elseif (isset($_POST['add_setting'])) {
        $newKey = $_POST['new_key'] ?? '';
        $newValue = $_POST['new_value'] ?? '';
        $newType = $_POST['new_type'] ?? 'text';
        $newCategory = $_POST['new_category'] ?? 'general';
        
        if ($newKey) {
            $stmt = $db->prepare('INSERT INTO settings (key, value, type, category) VALUES (?, ?, ?, ?)');
            $stmt->execute([$newKey, $newValue, $newType, $newCategory]);
            $message = 'Nowe ustawienie dodane.';
        }
    }
}

// Pobierz wszystkie ustawienia
$stmt = $db->query('SELECT * FROM settings ORDER BY category, key');
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grupuj ustawienia według kategorii
$categories = [];
foreach ($settings as $setting) {
    $category = $setting['category'] ?? 'general';
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][] = $setting;
}

// Pobierz statystyki systemu
$systemInfo = [
    'php_version' => phpversion(),
    'sqlite_version' => $db->query('SELECT sqlite_version() as version')->fetch()['version'],
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'db_size' => file_exists(DB_FILE) ? round(filesize(DB_FILE) / 1024, 2) . ' KB' : 'Nie istnieje',
    'cache_files' => is_dir(CACHE_DIR) ? count(glob(CACHE_DIR . '/*.cache')) : 0,
    'backup_files' => is_dir(DATA_DIR . '/backups') ? count(glob(DATA_DIR . '/backups/*.db')) : 0,
    'articles_count' => $db->query('SELECT COUNT(*) as count FROM articles')->fetch()['count'],
    'categories_count' => $db->query('SELECT COUNT(*) as count FROM categories')->fetch()['count'],
    'forms_count' => $db->query('SELECT COUNT(*) as count FROM form_submissions')->fetch()['count']
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .settings-card { border-left: 4px solid #667eea; }
        .system-info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= h($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Ustawienia -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Ustawienia systemu</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php foreach ($categories as $categoryName => $categorySettings): ?>
                                    <div class="card mb-3 settings-card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><?= ucfirst($categoryName) ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($categorySettings as $setting): ?>
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <?= h($setting['key']) ?>
                                                    <?php if ($setting['type']): ?>
                                                    <span class="badge bg-info float-end"><?= h($setting['type']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($setting['type'] === 'html'): ?>
                                                <textarea name="setting_<?= h($setting['key']) ?>" 
                                                          class="form-control" rows="4"><?= h($setting['value']) ?></textarea>
                                                <?php elseif ($setting['key'] === 'admin_password'): ?>
                                                <input type="password" name="setting_<?= h($setting['key']) ?>" 
                                                       class="form-control" value="<?= h($setting['value']) ?>">
                                                <?php else: ?>
                                                <input type="text" name="setting_<?= h($setting['key']) ?>" 
                                                       class="form-control" value="<?= h($setting['value']) ?>">
                                                <?php endif; ?>
                                                
                                                <?php if ($setting['updated_at']): ?>
                                                <small class="text-muted">
                                                    Ostatnia zmiana: <?= date('d.m.Y H:i', strtotime($setting['updated_at'])) ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="save_settings" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Zapisz wszystkie ustawienia
                                        </button>
                                        
                                        <button type="submit" name="clear_cache" class="btn btn-outline-warning">
                                            <i class="bi bi-trash"></i> Wyczyść cache
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Dodaj nowe ustawienie -->
                                <hr class="my-4">
                                <h6>Dodaj nowe ustawienie</h6>
                                <form method="POST" class="row g-3">
                                    <div class="col-md-3">
                                        <input type="text" name="new_key" class="form-control" 
                                               placeholder="Klucz (np. site_title)" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="new_value" class="form-control" 
                                               placeholder="Wartość" required>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="new_type" class="form-select">
                                            <option value="text">text</option>
                                            <option value="html">html</option>
                                            <option value="json">json</option>
                                            <option value="boolean">boolean</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="new_category" class="form-select">
                                            <option value="general">general</option>
                                            <option value="seo">seo</option>
                                            <option value="contact">contact</option>
                                            <option value="forms">forms</option>
                                            <option value="integration">integration</option>
                                            <option value="content">content</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" name="add_setting" class="btn btn-success w-100">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informacje o systemie -->
                    <div class="col-md-4">
                        <div class="card system-info-card">
                            <div class="card-header">
                                <h5 class="mb-0">Informacje o systemie</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>PHP</th>
                                        <td><?= h($systemInfo['php_version']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>SQLite</th>
                                        <td><?= h($systemInfo['sqlite_version']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Serwer</th>
                                        <td><?= h($systemInfo['server_software']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Rozmiar bazy</th>
                                        <td><?= h($systemInfo['db_size']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Pliki cache</th>
                                        <td><?= h($systemInfo['cache_files']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Backupy</th>
                                        <td><?= h($systemInfo['backup_files']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Artykuły</th>
                                        <td><?= h($systemInfo['articles_count']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Kategorie</th>
                                        <td><?= h($systemInfo['categories_count']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Formularze</th>
                                        <td><?= h($systemInfo['forms_count']) ?></td>
                                    </tr>
                                </table>
                                
                                <div class="mt-4">
                                    <h6>Narzędzia systemowe</h6>
                                    <div class="d-grid gap-2">
                                        <form method="POST">
                                            <button type="submit" name="backup_db" class="btn btn-outline-primary w-100 mb-2">
                                                <i class="bi bi-download"></i> Backup bazy danych
                                            </button>
                                        </form>
                                        
                                        <a href="login.php?logout=1" class="btn btn-outline-danger w-100 mb-2">
                                            <i class="bi bi-arrow-clockwise"></i> Restart sesji
                                        </a>
                                        
                                        <button onclick="location.reload()" class="btn btn-outline-secondary w-100">
                                            <i class="bi bi-arrow-clockwise"></i> Odśwież stronę
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h6>Linki CEMR</h6>
                                    <div class="list-group">
                                        <a href="<?= CEMR_DEMO_URL ?>" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="bi bi-link-45deg"></i> Demo CEMR
                                        </a>
                                        <a href="<?= CEMR_REGISTER_URL ?>" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="bi bi-link-45deg"></i> Rejestracja CEMR
                                        </a>
                                        <a href="<?= CEMR_CONTACT_URL ?>" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="bi bi-link-45deg"></i> Kontakt CEMR
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mt-4 text-center text-muted small">
                                    <div>FlowQuest CMS v1.0</div>
                                    <div>© <?= date('Y') ?> Wszelkie prawa zastrzeżone</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Potwierdzenie przed czyszczeniem cache
        const clearCacheBtn = document.querySelector('button[name="clear_cache"]');
        if (clearCacheBtn) {
            clearCacheBtn.addEventListener('click', function(e) {
                if (!confirm('Czy na pewno chcesz wyczyścić cache? To nie usunie danych z bazy.')) {
                    e.preventDefault();
                }
            });
        }
        
        // Potwierdzenie przed backupem
        const backupBtn = document.querySelector('button[name="backup_db"]');
        if (backupBtn) {
            backupBtn.addEventListener('click', function(e) {
                if (!confirm('Utworzyć backup bazy danych?')) {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>