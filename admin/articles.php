<?php
require_once '../includes/config.php';
require_once '../includes/cms.php';

// Sprawdź autoryzację
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';

// Obsługa akcji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_article'])) {
        // Zapisz/aktualizuj artykuł
        $data = [
            'title_pl' => $_POST['title_pl'] ?? '',
            'excerpt_pl' => $_POST['excerpt_pl'] ?? '',
            'content_pl' => $_POST['content_pl'] ?? '',
            'category_id' => $_POST['category_id'] ?? null,
            'status' => $_POST['status'] ?? 'draft',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'focus_keyword' => $_POST['focus_keyword'] ?? '',
            'read_time' => $_POST['read_time'] ?? '',
            'author' => $_POST['author'] ?? 'FlowQuest Team'
        ];
        
        if (!empty($_POST['published_at'])) {
            $data['published_at'] = $_POST['published_at'];
        }
        
        // Generuj slug z tytułu jeśli pusty
        if (empty($_POST['slug'])) {
            $data['slug'] = generateSlug($data['title_pl']);
        } else {
            $data['slug'] = $_POST['slug'];
        }
        
        if (empty($id)) {
            // Nowy artykuł
            $stmt = $db->prepare('INSERT INTO articles 
                (slug, category_id, status, title_pl, excerpt_pl, content_pl, 
                 meta_title, meta_description, meta_keywords, focus_keyword,
                 read_time, author, published_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            $stmt->execute([
                $data['slug'], $data['category_id'], $data['status'], 
                $data['title_pl'], $data['excerpt_pl'], $data['content_pl'],
                $data['meta_title'], $data['meta_description'], $data['meta_keywords'],
                $data['focus_keyword'], $data['read_time'], $data['author'],
                $data['published_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            $message = 'Artykuł został dodany.';
        } else {
            // Aktualizacja artykułu
            $stmt = $db->prepare('UPDATE articles SET 
                slug = ?, category_id = ?, status = ?, title_pl = ?, excerpt_pl = ?, 
                content_pl = ?, meta_title = ?, meta_description = ?, meta_keywords = ?,
                focus_keyword = ?, read_time = ?, author = ?, published_at = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?');
            
            $stmt->execute([
                $data['slug'], $data['category_id'], $data['status'], 
                $data['title_pl'], $data['excerpt_pl'], $data['content_pl'],
                $data['meta_title'], $data['meta_description'], $data['meta_keywords'],
                $data['focus_keyword'], $data['read_time'], $data['author'],
                $data['published_at'] ?? date('Y-m-d H:i:s'),
                $id
            ]);
            
            $message = 'Artykuł został zaktualizowany.';
        }
        
        // Przejdź do listy z komunikatem
        header('Location: articles.php?message=' . urlencode($message));
        exit;
        
    } elseif (isset($_POST['delete_article'])) {
        // Usuń artykuł
        $stmt = $db->prepare('DELETE FROM articles WHERE id = ?');
        $stmt->execute([$id]);
        
        header('Location: articles.php?message=Artykuł+usunięty');
        exit;
    }
}

// Pobierz dane w zależności od akcji
if ($action === 'edit' && $id) {
    $stmt = $db->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        header('Location: articles.php');
        exit;
    }
}

// Pobierz wszystkie kategorie
$stmt = $db->query('SELECT * FROM categories ORDER BY sort_order');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz listę artykułów (dla akcji list)
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = 'SELECT a.*, c.name_pl as category_name 
            FROM articles a 
            LEFT JOIN categories c ON a.category_id = c.id 
            WHERE 1=1';
    $params = [];
    
    if ($search) {
        $sql .= ' AND (a.title_pl LIKE ? OR a.excerpt_pl LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category) {
        $sql .= ' AND a.category_id = ?';
        $params[] = $category;
    }
    
    if ($status) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }
    
    $sql .= ' ORDER BY a.created_at DESC';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie artykułami - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-actions { white-space: nowrap; }
        .status-badge { font-size: 0.8em; }
        .form-label { font-weight: 500; }
        .form-control, .form-select { border-radius: 8px; }
        .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <!-- Komunikat -->
                <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= h($_GET['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($action === 'list'): ?>
                <!-- Lista artykułów -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Artykuły</h5>
                        <a href="articles.php?action=new" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nowy artykuł
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Filtry -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Szukaj w tytułach..." value="<?= h($_GET['search'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-select">
                                    <option value="">Wszystkie kategorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= h($cat['name_pl']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">Wszystkie statusy</option>
                                    <option value="draft" <?= ($_GET['status'] ?? '') == 'draft' ? 'selected' : '' ?>>Wersja robocza</option>
                                    <option value="published" <?= ($_GET['status'] ?? '') == 'published' ? 'selected' : '' ?>>Opublikowany</option>
                                    <option value="archived" <?= ($_GET['status'] ?? '') == 'archived' ? 'selected' : '' ?>>Archiwalny</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-filter"></i> Filtruj
                                </button>
                            </div>
                        </form>
                        
                        <!-- Tabela artykułów -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tytuł</th>
                                        <th>Kategoria</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Wyświetlenia</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($articles)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Brak artykułów. Dodaj pierwszy artykuł!
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($articles as $article): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($article['title_pl']) ?></strong><br>
                                            <small class="text-muted"><?= h($article['slug']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($article['category_name']): ?>
                                            <span class="badge bg-info"><?= h($article['category_name']) ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">Brak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($article['status'] === 'published'): ?>
                                            <span class="badge bg-success status-badge">Opublikowany</span>
                                            <?php elseif ($article['status'] === 'draft'): ?>
                                            <span class="badge bg-secondary status-badge">Wersja robocza</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning status-badge">Archiwalny</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('d.m.Y', strtotime($article['created_at'])) ?><br>
                                            <small><?= $article['read_time'] ?></small>
                                        </td>
                                        <td><?= h($article['views_count']) ?></td>
                                        <td class="table-actions">
                                            <a href="articles.php?action=edit&id=<?= $article['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edytuj">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="../blog-details.html?article=<?= $article['slug'] ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-success" title="Podgląd">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form method="POST" action="articles.php?action=edit&id=<?= $article['id'] ?>" 
                                                  class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć ten artykuł?');">
                                                <input type="hidden" name="delete_article" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Usuń">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Formularz edycji/dodawania artykułu -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= $action === 'new' ? 'Nowy artykuł' : 'Edytuj artykuł' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Tytuł i treść -->
                                    <div class="mb-3">
                                        <label class="form-label">Tytuł artykułu *</label>
                                        <input type="text" name="title_pl" class="form-control" 
                                               value="<?= h($article['title_pl'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Skrót (excerpt)</label>
                                        <textarea name="excerpt_pl" class="form-control" rows="3"><?= h($article['excerpt_pl'] ?? '') ?></textarea>
                                        <small class="text-muted">Krótki opis widoczny na liście artykułów (max 160 znaków)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Treść artykułu *</label>
                                        <textarea name="content_pl" class="form-control" rows="15" required><?= h($article['content_pl'] ?? '') ?></textarea>
                                        <small class="text-muted">Możesz używać HTML dla formatowania</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <!-- Ustawienia boczne -->
                                    <div class="mb-3">
                                        <label class="form-label">Slug (URL)</label>
                                        <input type="text" name="slug" class="form-control" 
                                               value="<?= h($article['slug'] ?? '') ?>">
                                        <small class="text-muted">Tylko małe litery, cyfry i myślniki</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kategoria</label>
                                        <select name="category_id" class="form-select">
                                            <option value="">Wybierz kategorię</option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" 
                                                <?= ($article['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                <?= h($cat['name_pl']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="draft" <?= ($article['status'] ?? 'draft') == 'draft' ? 'selected' : '' ?>>Wersja robocza</option>
                                            <option value="published" <?= ($article['status'] ?? '') == 'published' ? 'selected' : '' ?>>Opublikowany</option>
                                            <option value="archived" <?= ($article['status'] ?? '') == 'archived' ? 'selected' : '' ?>>Archiwalny</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Data publikacji</label>
                                        <input type="datetime-local" name="published_at" class="form-control"
                                               value="<?= isset($article['published_at']) ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : date('Y-m-d\TH:i') ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Czas czytania</label>
                                        <input type="text" name="read_time" class="form-control" 
                                               value="<?= h($article['read_time'] ?? '5 min') ?>" placeholder="np. 5 min">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Autor</label>
                                        <input type="text" name="author" class="form-control" 
                                               value="<?= h($article['author'] ?? 'FlowQuest Team') ?>">
                                    </div>
                                    
                                    <!-- SEO -->
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">SEO</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <label class="form-label">Meta tytuł</label>
                                                <input type="text" name="meta_title" class="form-control" 
                                                       value="<?= h($article['meta_title'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="form-label">Meta opis</label>
                                                <textarea name="meta_description" class="form-control" rows="3"><?= h($article['meta_description'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="form-label">Słowa kluczowe</label>
                                                <input type="text" name="meta_keywords" class="form-control" 
                                                       value="<?= h($article['meta_keywords'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="form-label">Focus keyword</label>
                                                <input type="text" name="focus_keyword" class="form-control" 
                                                       value="<?= h($article['focus_keyword'] ?? '') ?>">
                                                <small class="text-muted">Główne słowo kluczowe</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="articles.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Powrót do listy
                                </a>
                                <button type="submit" name="save_article" class="btn btn-save px-4">
                                    <i class="bi bi-save"></i> Zapisz artykuł
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-generate slug from title
        const titleInput = document.querySelector('input[name="title_pl"]');
        const slugInput = document.querySelector('input[name="slug"]');
        
        if (titleInput && slugInput) {
            titleInput.addEventListener('blur', function() {
                if (!slugInput.value) {
                    const title = this.value.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .trim();
                    slugInput.value = title;
                }
            });
        }
        
        // Character counters
        const excerptTextarea = document.querySelector('textarea[name="excerpt_pl"]');
        const metaDescTextarea = document.querySelector('textarea[name="meta_description"]');
        
        function addCounter(element, maxChars) {
            const counter = document.createElement('small');
            counter.className = 'text-muted float-end';
            counter.textContent = `0/${maxChars} znaków`;
            
            element.parentNode.appendChild(counter);
            
            element.addEventListener('input', function() {
                const count = this.value.length;
                counter.textContent = `${count}/${maxChars} znaków`;
                counter.className = `text-${count > maxChars ? 'danger' : 'muted'} float-end`;
            });
            
            // Trigger initial count
            element.dispatchEvent(new Event('input'));
        }
        
        if (excerptTextarea) addCounter(excerptTextarea, 160);
        if (metaDescTextarea) addCounter(metaDescTextarea, 160);
    </script>
</body>
</html>