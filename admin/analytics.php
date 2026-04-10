<?php
require_once '../includes/config.php';
require_once '../includes/cms.php';
require_once '../includes/ga4_integration.php';

// Sprawdź autoryzację
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Pobierz parametry
$period = $_GET['period'] ?? '30d'; // 7d, 30d, 90d, ytd
$category = $_GET['category'] ?? 'all';
$sort = $_GET['sort'] ?? 'score'; // score, views, time, conversion, ai
$action = $_GET['action'] ?? 'dashboard';

// Mapuj period na dni
$periodMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    'ytd' => (int)date('z') + 1
];
$days = $periodMap[$period] ?? 30;

// Obsługa akcji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_ga4'])) {
        // Synchronizuj z GA4
        $ga4 = initGA4();
        if ($ga4) {
            $result = $ga4->syncToDatabase($days);
            $message = $result['message'] ?? 'Synchronizacja wykonana';
            $syncStatus = $result['success'] ? 'success' : 'error';
        } else {
            $message = 'GA4 nie jest skonfigurowany. Skonfiguruj w ustawieniach.';
            $syncStatus = 'error';
        }
    } elseif (isset($_POST['update_ai_citation'])) {
        // Ręczna aktualizacja cytowań AI
        $articleId = $_POST['article_id'] ?? 0;
        $aiModel = $_POST['ai_model'] ?? 'unknown';
        $citationContext = $_POST['citation_context'] ?? '';
        
        if ($articleId) {
            $stmt = $db->prepare('INSERT INTO ai_citation_log (article_id, citation_date, ai_model, citation_context) VALUES (?, ?, ?, ?)');
            $stmt->execute([$articleId, date('Y-m-d'), $aiModel, $citationContext]);
            $message = 'Cytowanie AI dodane';
            $syncStatus = 'success';
        }
    }
}

// Pobierz statystyki ogólne
$stmt = $db->query("
    SELECT 
        COUNT(DISTINCT a.id) as total_articles,
        COALESCE(SUM(ap.pageviews), 0) as total_views,
        COALESCE(AVG(ap.avg_time_on_page), 0) as avg_time,
        COALESCE(AVG(ap.bounce_rate), 0) as avg_bounce,
        COALESCE(SUM(ap.demo_requests), 0) as total_demos,
        COALESCE(SUM(acl.id), 0) as total_ai_citations
    FROM articles a
    LEFT JOIN article_performance ap ON ap.article_id = a.id AND ap.date >= date('now', '-{$days} days')
    LEFT JOIN ai_citation_log acl ON acl.article_id = a.id
    WHERE a.status = 'published'
");
$overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Pobierz artykuły do porównania
$sql = "
    SELECT 
        a.id,
        a.title_pl,
        a.slug,
        a.status,
        a.created_at,
        a.published_at,
        c.name_pl as category_name,
        c.id as category_id,
        COALESCE(SUM(ap.pageviews), 0) as total_views,
        COALESCE(AVG(ap.avg_time_on_page), 0) as avg_time,
        COALESCE(AVG(ap.bounce_rate), 0) as bounce_rate,
        COALESCE(SUM(ap.demo_requests), 0) as demo_requests,
        COALESCE(COUNT(acl.id), 0) as ai_citations,
        a.views_count as all_time_views
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN article_performance ap ON ap.article_id = a.id AND ap.date >= date('now', '-{$days} days')
    LEFT JOIN ai_citation_log acl ON acl.article_id = a.id
    WHERE a.status = 'published'
";

if ($category !== 'all') {
    $sql .= " AND c.id = " . intval($category);
}

$sql .= " GROUP BY a.id";

// Sortowanie
$sortMap = [
    'score' => " ORDER BY (COALESCE(SUM(ap.pageviews), 0) * 0.3 + COALESCE(AVG(ap.avg_time_on_page), 0) * 0.2 + (100 - COALESCE(AVG(ap.bounce_rate), 100)) * 0.2 + COALESCE(SUM(ap.demo_requests), 0) * 0.3) DESC",
    'views' => " ORDER BY COALESCE(SUM(ap.pageviews), 0) DESC",
    'time' => " ORDER BY COALESCE(AVG(ap.avg_time_on_page), 0) DESC",
    'conversion' => " ORDER BY COALESCE(SUM(ap.demo_requests), 0) DESC",
    'ai' => " ORDER BY COALESCE(COUNT(acl.id), 0) DESC",
    'newest' => " ORDER BY a.published_at DESC"
];

$sql .= $sortMap[$sort] ?? $sortMap['score'];

$stmt = $db->query($sql);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Oblicz score dla każdego artykułu
foreach ($articles as &$article) {
    $score = calculateArticleScore([
        'total_views' => $article['total_views'],
        'avg_time' => $article['avg_time'],
        'bounce_rate' => $article['bounce_rate'],
        'demo_requests' => $article['demo_requests'],
        'ai_citations' => $article['ai_citations']
    ]);
    
    $article['score'] = $score['total'];
    $article['score_details'] = $score['components'];
    $article['trend'] = getArticleTrends($article['id'], min($days, 7));
}

// Pobierz kategorie dla filtru
$stmt = $db->query('SELECT id, name_pl FROM categories ORDER BY sort_order');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz najlepsze artykuły według kategorii
$topByCategory = getTopArticlesByCategory($days, 3);

// Pobierz statystyki źródeł ruchu
$stmt = $db->query("
    SELECT 
        source_type,
        medium,
        SUM(sessions) as sessions,
        SUM(users) as users,
        SUM(new_users) as new_users,
        AVG(bounce_rate) as bounce_rate,
        SUM(goal_completions) as conversions
    FROM traffic_sources 
    WHERE date >= date('now', '-{$days} days')
    GROUP BY source_type, medium
    ORDER BY sessions DESC
    LIMIT 10
");
$trafficSources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sprawdź status GA4
$ga4Enabled = !empty(getSetting('ga4_measurement_id', '')) && getSetting('ga4_measurement_id', '') !== 'G-XXXXXXXXXX';
$lastSync = $db->query("SELECT MAX(sync_date) as last_sync FROM ga_sync_log WHERE sync_status = 'success'")->fetch()['last_sync'] ?? null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analityka - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 10px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .score-badge { font-size: 1.2rem; font-weight: bold; }
        .score-excellent { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .score-good { background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white; }
        .score-average { background: linear-gradient(135deg, #ffc107, #ffca2c); color: black; }
        .score-poor { background: linear-gradient(135deg, #fd7e14, #ff922b); color: white; }
        .score-bad { background: linear-gradient(135deg, #dc3545, #e35d6a); color: white; }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
        .chart-container { height: 300px; position: relative; }
        .progress-thin { height: 6px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <?php if (isset($message)): ?>
                <div class="alert alert-<?= $syncStatus === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= h($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="mb-0">Analityka artykułów</h3>
                        <p class="text-muted mb-0">Śledź wydajność, porównuj artykuły i optymalizuj pod AI</p>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="sync_ga4" class="btn btn-outline-primary" <?= !$ga4Enabled ? 'disabled' : '' ?>>
                                <i class="bi bi-arrow-repeat"></i> Synchronizuj z GA4
                            </button>
                        </form>
                        
                        <a href="analytics.php?action=ai_optimization" class="btn btn-outline-success">
                            <i class="bi bi-robot"></i> Optymalizacja AI
                        </a>
                    </div>
                </div>
                
                <!-- Status GA4 -->
                <?php if (!$ga4Enabled): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Google Analytics 4 nie jest skonfigurowany. 
                    <a href="settings.php" class="alert-link">Skonfiguruj w ustawieniach</a> aby śledzić statystyki.
                </div>
                <?php elseif ($lastSync): ?>
                <div class="alert alert-info">
                    <i class="bi bi-check-circle"></i> 
                    Ostatnia synchronizacja: <?= date('d.m.Y H:i', strtotime($lastSync)) ?>
                </div>
                <?php endif; ?>
                
                <!-- Filtry -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Okres</label>
                                <select name="period" class="form-select" onchange="this.form.submit()">
                                    <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Ostatnie 7 dni</option>
                                    <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Ostatnie 30 dni</option>
                                    <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Ostatnie 90 dni</option>
                                    <option value="ytd" <?= $period === 'ytd' ? 'selected' : '' ?>>Od początku roku</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Kategoria</label>
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <option value="all">Wszystkie kategorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= h($cat['name_pl']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Sortowanie</label>
                                <select name="sort" class="form-select" onchange="this.form.submit()">
                                    <option value="score" <?= $sort === 'score' ? 'selected' : '' ?>>Najlepsze ogólnie</option>
                                    <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Najwięcej wyświetleń</option>
                                    <option value="time" <?= $sort === 'time' ? 'selected' : '' ?>>Najdłuższy czas</option>
                                    <option value="conversion" <?= $sort === 'conversion' ? 'selected' : '' ?>>Najwięcej konwersji</option>
                                    <option value="ai" <?= $sort === 'ai' ? 'selected' : '' ?>>Najwięcej cytowań AI</option>
                                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnowsze</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filtruj
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Statystyki ogólne -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Artykuły</h6>
                                        <h3 class="mb-0"><?= number_format($overallStats['total_articles']) ?></h3>
                                    </div>
                                    <i class="bi bi-file-earmark-text" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                                <div class="mt-2 small">
                                    Opublikowane
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Wyświetlenia</h6>
                                        <h3 class="mb-0"><?= number_format($overallStats['total_views']) ?></h3>
                                    </div>
                                    <i class="bi bi-eye" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                                <div class="mt-2 small">
                                    Średnio <?= $overallStats['total_articles'] > 0 ? number_format($overallStats['total_views'] / $overallStats['total_articles']) : 0 ?> na artykuł
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Czas na stronie</h6>
                                        <h3 class="mb-0"><?= round($overallStats['avg_time']) ?>s</h3>
                                    </div>
                                    <i class="bi bi-clock" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                                <div class="mt-2 small">
                                    Bounce rate: <?= round($overallStats['avg_bounce'], 1) ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Cytowania AI</h6>
                                        <h3 class="mb-0"><?= number_format($overallStats['total_ai_citations']) ?></h3>
                                    </div>
                                    <i class="bi bi-robot" style="font-size: 2rem; opacity: 0.7;"></i>
                                </div>
                                <div class="mt-2 small">
                                    Demo: <?= number_format($overallStats['total_demos']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela porównania artykułów -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Porównanie artykułów</h5>
                        <p class="text-muted small mb-0">Sortuj i analizuj aby znaleźć najlepsze wzorce</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="30%">Artykuł</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Wyświetlenia</th>
                                        <th class="text-center">Czas</th>
                                        <th class="text-center">Bounce</th>
                                        <th class="text-center">Demo</th>
                                        <th class="text-center">AI</th>
                                        <th class="text-center">Trend</th>
                                        <th class="text-center">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($articles as $article): ?>
                                    <?php 
                                        $scoreClass = '';
                                        if ($article['score'] >= 80) $scoreClass = 'score-excellent';
                                        elseif ($article['score'] >= 60) $scoreClass = 'score-good';
                                        elseif ($article['score'] >= 40) $scoreClass = 'score-average';
                                        elseif ($article['score'] >= 20) $scoreClass = 'score-poor';
                                        else $scoreClass = 'score-bad';
                                        
                                        $trendClass = 'trend-' . ($article['trend']['trend'] ?? 'stable');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <span class="badge <?= $scoreClass ?> score-badge"><?= round($article['score']) ?></span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong><?= h($article['title_pl']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= h($article['category_name']) ?> • 
                                                        <?= date('d.m.Y', strtotime($article['published_at'] ?? $article['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $article['score'] ?>%"
                                                     title="Zaangażowanie: <?= round($article['score_details']['engagement'] ?? 0) ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= round($article['score']) ?>/100</small>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= number_format($article['total_views']) ?></strong><br>
                                            <small class="text-muted"><?= number_format($article['all_time_views']) ?> łącznie</small>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= round($article['avg_time']) ?>s</strong><br>
                                            <small class="text-muted">średnio</small>
                                        </td>
                                        <td class="text-center">
                                            <strong class="<?= $article['bounce_rate'] < 50 ? 'text-success' : ($article['bounce_rate'] < 70 ? 'text-warning' : 'text-danger') ?>">
                                                <?= round($article['bounce_rate'], 1) ?>%
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= number_format($article['demo_requests']) ?></strong><br>
                                            <small class="text-muted">
                                                <?= $article['total_views'] > 0 ? round(($article['demo_requests'] / $article['total_views']) * 100, 2) : 0 ?>%
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= number_format($article['ai_citations']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($article['trend']['change'] ?? 0) > 0): ?>
                                            <span class="<?= $trendClass ?>">
                                                <i class="bi bi-arrow-up-right"></i> +<?= abs(round($article['trend']['change'] ?? 0)) ?>%
                                            </span>
                                            <?php elseif (($article['trend']['change'] ?? 0) < 0): ?>
                                            <span class="<?= $trendClass ?>">
                                                <i class="bi bi-arrow-down-right"></i> -<?= abs(round($article['trend']['change'] ?? 0)) ?>%
                                            </span>
                                            <?php else: ?>
                                            <span class="<?= $trendClass ?>">→</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="articles.php?action=edit&id=<?= $article['id'] ?>" 
                                                   class="btn btn-outline-primary" title="Edytuj">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="../blog-details.html?article=<?= $article['slug'] ?>" 
                                                   target="_blank" class="btn btn-outline-success" title="Podgląd">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-info" 
                                                        data-bs-toggle="modal" data-bs-target="#detailsModal<?= $article['id'] ?>"
                                                        title="Szczegóły">
                                                    <i class="bi bi-bar-chart"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal ze szczegółami -->
                                    <div class="modal fade" id="detailsModal<?= $article['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Szczegóły: <?= h($article['title_pl']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Statystyki</h6>
                                                            <table class="table table-sm">
                                                                <tr><td>Wyświetlenia:</td><td><strong><?= number_format($article['total_views']) ?></strong></td></tr>
                                                                <tr><td>Średni czas:</td><td><strong><?= round($article['avg_time']) ?> sekund</strong></td></tr>
                                                                <tr><td>Bounce rate:</td><td><strong><?= round($article['bounce_rate'], 1) ?>%</strong></td></tr>
                                                                <tr><td>Demo requests:</td><td><strong><?= number_format($article['demo_requests']) ?></strong></td></tr>
                                                                <tr><td>Cytowania AI:</td><td><strong><?= number_format($article['ai_citations']) ?></strong></td></tr>
                                                                <tr><td>Conversion rate:</td><td><strong>
                                                                    <?= $article['total_views'] > 0 ? round(($article['demo_requests'] / $article['total_views']) * 100, 2) : 0 ?>%
                                                                </strong></td></tr>
                                                            </table>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Składowe score</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td>Zaangażowanie:</td>
                                                                    <td>
                                                                        <div class="progress progress-thin">
                                                                            <div class="progress-bar" style="width: <?= $article['score_details']['engagement'] ?? 0 ?>%"></div>
                                                                        </div>
                                                                        <small><?= round($article['score_details']['engagement'] ?? 0) ?>%</small>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Wyświetlenia:</td>
                                                                    <td>
                                                                        <div class="progress progress-thin">
                                                                            <div class="progress-bar bg-success" style="width: <?= $article['score_details']['views'] ?? 0 ?>%"></div>
                                                                        </div>
                                                                        <small><?= round($article['score_details']['views'] ?? 0) ?>%</small>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Konwersje:</td>
                                                                    <td>
                                                                        <div class="progress progress-thin">
                                                                            <div class="progress-bar bg-info" style="width: <?= $article['score_details']['conversion'] ?? 0 ?>%"></div>
                                                                        </div>
                                                                        <small><?= round($article['score_details']['conversion'] ?? 0) ?>%</small>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>AI:</td>
                                                                    <td>
                                                                        <div class="progress progress-thin">
                                                                            <div class="progress-bar bg-warning" style="width: <?= $article['score_details']['ai'] ?? 0 ?>%"></div>
                                                                        </div>
                                                                        <small><?= round($article['score_details']['ai'] ?? 0) ?>%</small>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Formularz dodawania cytowania AI -->
                                                    <hr>
                                                    <h6>Dodaj cytowanie AI</h6>
                                                    <form method="POST" class="row g-3">
                                                        <input type="hidden" name="article_id" value="<?= $article['id'] ?>">
                                                        <div class="col-md-4">
                                                            <select name="ai_model" class="form-select" required>
                                                                <option value="">Wybierz model AI</option>
                                                                <option value="chatgpt">ChatGPT</option>
                                                                <option value="claude">Claude</option>
                                                                <option value="gemini">Gemini</option>
                                                                <option value="copilot">Copilot</option>
                                                                <option value="perplexity">Perplexity</option>
                                                                <option value="other">Inny</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <input type="text" name="citation_context" class="form-control" 
                                                                   placeholder="Kontekst cytowania (np. 'rozwiązanie dla agencji IT')" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <button type="submit" name="update_ai_citation" class="btn btn-primary w-100">
                                                                <i class="bi bi-plus"></i> Dodaj
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($articles)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart" style="font-size: 3rem;"></i>
                            <p class="mt-3">Brak danych analitycznych. Spróbuj zsynchronizować z Google Analytics.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Najlepsze artykuły według kategorii -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Liderzy w kategoriach</h5>
                                <p class="text-muted small mb-0">Top 3 artykuły w każdej kategorii</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($topByCategory as $categoryId => $categoryData): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 border-primary">
                                            <div class="card-header bg-primary text-white py-2">
                                                <h6 class="mb-0"><?= h($categoryData['category_name']) ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($categoryData['articles'])): ?>
                                                <p class="text-muted text-center py-3">Brak artykułów</p>
                                                <?php else: ?>
                                                <div class="list-group list-group-flush">
                                                    <?php foreach ($categoryData['articles'] as $article): ?>
                                                    <div class="list-group-item border-0 px-0 py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="flex-grow-1">
                                                                <a href="articles.php?action=edit&id=<?= $article['article_id'] ?>" 
                                                                   class="text-decoration-none">
                                                                    <?= h(substr($article['title_pl'], 0, 40)) ?><?= strlen($article['title_pl']) > 40 ? '...' : '' ?>
                                                                </a>
                                                                <div class="small text-muted">
                                                                    <?= number_format($article['total_views']) ?> wyświetleń
                                                                </div>
                                                            </div>
                                                            <span class="badge bg-success"><?= round($article['total_views'] > 0 ? ($article['demo_requests'] / $article['total_views']) * 100 : 0, 1) ?>%</span>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Źródła ruchu -->
                <?php if (!empty($trafficSources)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Źródła ruchu</h5>
                        <p class="text-muted small mb-0">Skąd przychodzą użytkownicy</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Źródło</th>
                                        <th class="text-center">Sesje</th>
                                        <th class="text-center">Użytkownicy</th>
                                        <th class="text-center">Nowi</th>
                                        <th class="text-center">Bounce</th>
                                        <th class="text-center">Konwersje</th>
                                        <th class="text-center">Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trafficSources as $source): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info"><?= h($source['source_type']) ?></span>
                                            <span class="badge bg-secondary"><?= h($source['medium']) ?></span>
                                        </td>
                                        <td class="text-center"><?= number_format($source['sessions']) ?></td>
                                        <td class="text-center"><?= number_format($source['users']) ?></td>
                                        <td class="text-center"><?= number_format($source['new_users']) ?></td>
                                        <td class="text-center"><?= round($source['bounce_rate'], 1) ?>%</td>
                                        <td class="text-center"><?= number_format($source['conversions']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= ($source['conversions'] / max(1, $source['sessions']) * 100) > 5 ? 'bg-success' : 'bg-warning' ?>">
                                                <?= round($source['conversions'] / max(1, $source['sessions']) * 100, 1) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Auto-refresh co 5 minut
        setTimeout(function() {
            window.location.reload();
        }, 300000);
        
        // Inicjalizuj tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
        
        // Wykresy (opcjonalne)
        document.addEventListener('DOMContentLoaded', function() {
            const scoreCtx = document.getElementById('scoreChart');
            if (scoreCtx) {
                new Chart(scoreCtx, {
                    type: 'radar',
                    data: {
                        labels: ['Wyświetlenia', 'Zaangażowanie', 'Konwersje', 'AI', 'SEO'],
                        datasets: [{
                            label: 'Średnie score',
                            data: [65, 72, 58, 45, 68],
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>