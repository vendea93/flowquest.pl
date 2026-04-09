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
    if (isset($_POST['save_category'])) {
        $data = [
            'name_pl' => $_POST['name_pl'] ?? '',
            'description' => $_POST['description'] ?? '',
            'sort_order' => $_POST['sort_order'] ?? 0
        ];
        
        // Generuj slug z nazwy
        if (empty($_POST['slug'])) {
            $data['slug'] = generateSlug($data['name_pl']);
        } else {
            $data['slug'] = $_POST['slug'];
        }
        
        if (empty($id)) {
            // Nowa kategoria
            $stmt = $db->prepare('INSERT INTO categories (slug, name_pl, description, sort_order) 
                                  VALUES (?, ?, ?, ?)');
            $stmt->execute([$data['slug'], $data['name_pl'], $data['description'], $data['sort_order']]);
            $message = 'Kategoria dodana.';
        } else {
            // Aktualizacja
            $stmt = $db->prepare('UPDATE categories SET slug = ?, name_pl = ?, description = ?, sort_order = ? 
                                  WHERE id = ?');
            $stmt->execute([$data['slug'], $data['name_pl'], $data['description'], $data['sort_order'], $id]);
            $message = 'Kategoria zaktualizowana.';
        }
        
        header('Location: categories.php?message=' . urlencode($message));
        exit;
        
    } elseif (isset($_POST['delete_category'])) {
        // Sprawdź czy kategoria ma artykuły
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM articles WHERE category_id = ?');
        $stmt->execute([$id]);
        $articleCount = $stmt->fetch()['count'];
        
        if ($articleCount > 0) {
            $message = 'Nie można usunąć kategorii która ma artykuły. Najpierw przenieś artykuły do innej kategorii.';
        } else {
            $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Kategoria usunięta.';
        }
        
        header('Location: categories.php?message=' . urlencode($message));
        exit;
    }
}

// Pobierz dane w zależności od akcji
if ($action === 'edit' && $id) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header('Location: categories.php');
        exit;
    }
}

// Pobierz listę kategorii
if ($action === 'list') {
    $stmt = $db->query('SELECT c.*, 
        (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) as article_count 
        FROM categories c 
        ORDER BY c.sort_order, c.name_pl');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .category-card { border-left: 4px solid #667eea; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= h($_GET['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($action === 'list'): ?>
                <!-- Lista kategorii -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Kategorie branżowe</h5>
                        <a href="categories.php?action=new" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nowa kategoria
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-tag" style="font-size: 3rem;"></i>
                            <p class="mt-3">Brak kategorii. Dodaj pierwszą kategorie branżową.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($categories as $cat): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card category-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title mb-1"><?= h($cat['name_pl']) ?></h6>
                                                <p class="card-text small text-muted mb-2"><?= h($cat['description']) ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-info"><?= h($cat['slug']) ?></span>
                                                    <span class="badge bg-secondary"><?= $cat['article_count'] ?> artykułów</span>
                                                    <span class="badge bg-light text-dark">Kolejność: <?= $cat['sort_order'] ?></span>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                        data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="categories.php?action=edit&id=<?= $cat['id'] ?>">
                                                            <i class="bi bi-pencil me-2"></i> Edytuj
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="articles.php?category=<?= $cat['id'] ?>">
                                                            <i class="bi bi-file-earmark-text me-2"></i> Artykuły
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="categories.php?action=edit&id=<?= $cat['id'] ?>" 
                                                              onsubmit="return confirm('Czy na pewno chcesz usunąć tę kategorię?');">
                                                            <input type="hidden" name="delete_category" value="1">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i> Usuń
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Formularz edycji/dodawania kategorii -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= $action === 'new' ? 'Nowa kategoria' : 'Edytuj kategorię' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Nazwa kategorii *</label>
                                        <input type="text" name="name_pl" class="form-control" 
                                               value="<?= h($category['name_pl'] ?? '') ?>" required 
                                               placeholder="np. IT & Software Development">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Opis kategorii</label>
                                        <textarea name="description" class="form-control" rows="3"><?= h($category['description'] ?? '') ?></textarea>
                                        <small class="text-muted">Krótki opis wyświetlany w metadanych</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Slug (URL) *</label>
                                        <input type="text" name="slug" class="form-control" 
                                               value="<?= h($category['slug'] ?? '') ?>" required 
                                               placeholder="np. it-software">
                                        <small class="text-muted">Tylko małe litery, cyfry i myślniki</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kolejność sortowania</label>
                                        <input type="number" name="sort_order" class="form-control" 
                                               value="<?= h($category['sort_order'] ?? 0) ?>" min="0" max="100">
                                        <small class="text-muted">0 = pierwsza, 100 = ostatnia</small>
                                    </div>
                                    
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Przykłady branż:</h6>
                                            <ul class="small mb-0">
                                                <li>IT & Software Development</li>
                                                <li>Marketing & Agencje</li>
                                                <li>Produkcja & Manufacturing</li>
                                                <li>Usługi B2B & Konsulting</li>
                                                <li>E-commerce</li>
                                                <li>Edukacja & Szkolenia</li>
                                                <li>Finanse & Księgowość</li>
                                                <li>Creative & Design</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="categories.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Powrót do listy
                                </a>
                                <button type="submit" name="save_category" class="btn btn-primary px-4">
                                    <i class="bi bi-save"></i> Zapisz kategorię
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
        // Auto-generate slug from category name
        const nameInput = document.querySelector('input[name="name_pl"]');
        const slugInput = document.querySelector('input[name="slug"]');
        
        if (nameInput && slugInput) {
            nameInput.addEventListener('blur', function() {
                if (!slugInput.value) {
                    const slug = this.value.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .trim();
                    slugInput.value = slug;
                }
            });
        }
    </script>
</body>
</html>