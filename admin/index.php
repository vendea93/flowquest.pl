<?php
require_once '../includes/config.php';
require_once '../includes/cms.php';

// Sprawdź autoryzację
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Pobierz statystyki
$stats = [];

// Liczba artykułów
$stmt = $db->query('SELECT COUNT(*) as total, 
    SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft,
    SUM(views_count) as views 
    FROM articles');
$stats['articles'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Liczba kategorii
$stmt = $db->query('SELECT COUNT(*) as count FROM categories');
$stats['categories'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Nowe formularze (ostatnie 7 dni)
$stmt = $db->query('SELECT COUNT(*) as count FROM form_submissions 
    WHERE created_at >= datetime("now", "-7 days")');
$stats['forms'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Ostatnie artykuły
$stmt = $db->query('SELECT a.*, c.name_pl as category_name 
    FROM articles a 
    LEFT JOIN categories c ON a.category_id = c.id 
    ORDER BY created_at DESC LIMIT 5');
$recentArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ostatnie formularze
$stmt = $db->query('SELECT * FROM form_submissions 
    ORDER BY created_at DESC LIMIT 5');
$recentForms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .stat-card { border-radius: 10px; transition: transform 0.2s; border: none; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <!-- Zawartość -->
                <div class="p-4">
                    <!-- Statystyki -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Artykuły</h5>
                                            <h2 class="mb-0"><?= h($stats['articles']['total'] ?? 0) ?></h2>
                                        </div>
                                        <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                    </div>
                                    <div class="mt-2">
                                        <small>
                                            <?= h($stats['articles']['published'] ?? 0) ?> opublikowanych |
                                            <?= h($stats['articles']['draft'] ?? 0) ?> wersji roboczych
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Wyświetlenia</h5>
                                            <h2 class="mb-0"><?= h($stats['articles']['views'] ?? 0) ?></h2>
                                        </div>
                                        <i class="bi bi-eye" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                    </div>
                                    <div class="mt-2">
                                        <small>Łącznie artykułów</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Kategorie</h5>
                                            <h2 class="mb-0"><?= h($stats['categories']['count'] ?? 0) ?></h2>
                                        </div>
                                        <i class="bi bi-tags" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                    </div>
                                    <div class="mt-2">
                                        <small>Branż w systemie</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Formularze</h5>
                                            <h2 class="mb-0"><?= h($stats['forms']['count'] ?? 0) ?></h2>
                                        </div>
                                        <i class="bi bi-envelope" style="font-size: 2.5rem; opacity: 0.7;"></i>
                                    </div>
                                    <div class="mt-2">
                                        <small>Ostatnie 7 dni</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Szybkie akcje -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Szybkie akcje</h5>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="articles.php?action=new" class="btn btn-primary">
                                            <i class="bi bi-plus-circle me-1"></i> Nowy artykuł
                                        </a>
                                        <a href="categories.php" class="btn btn-secondary">
                                            <i class="bi bi-tag me-1"></i> Zarządzaj kategoriami
                                        </a>
                                        <a href="forms.php" class="btn btn-success">
                                            <i class="bi bi-envelope me-1"></i> Przeglądaj formularze
                                        </a>
                                        <a href="settings.php" class="btn btn-outline-primary">
                                            <i class="bi bi-gear me-1"></i> Ustawienia
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Ostatnie artykuły -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title d-flex justify-content-between align-items-center">
                                        Ostatnie artykuły
                                        <a href="articles.php" class="btn btn-sm btn-outline-primary">Wszystkie</a>
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tytuł</th>
                                                    <th>Status</th>
                                                    <th>Data</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentArticles as $article): ?>
                                                <tr>
                                                    <td>
                                                        <a href="articles.php?action=edit&id=<?= $article['id'] ?>" 
                                                           class="text-decoration-none">
                                                            <?= h(substr($article['title_pl'], 0, 40)) ?><?= strlen($article['title_pl']) > 40 ? '...' : '' ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php if ($article['status'] === 'published'): ?>
                                                            <span class="badge bg-success">Opublikowany</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Wersja robocza</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= date('d.m.Y', strtotime($article['created_at'])) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ostatnie formularze -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title d-flex justify-content-between align-items-center">
                                        Ostatnie formularze
                                        <a href="forms.php" class="btn btn-sm btn-outline-success">Wszystkie</a>
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Typ</th>
                                                    <th>Email</th>
                                                    <th>Data</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentForms as $form): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= h($form['form_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= h($form['email']) ?></td>
                                                    <td><?= date('d.m.Y H:i', strtotime($form['created_at'])) ?></td>
                                                    <td>
                                                        <?php if ($form['status'] === 'new'): ?>
                                                            <span class="badge bg-warning">Nowy</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?= h($form['status']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
        // Auto refresh co 5 minut
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>