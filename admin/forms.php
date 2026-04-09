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

// Obsługa akcji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'] ?? 'new';
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $db->prepare('UPDATE form_submissions SET status = ?, notes = ? WHERE id = ?');
        $stmt->execute([$status, $notes, $id]);
        
        header('Location: forms.php?message=Status+zaktualizowany');
        exit;
        
    } elseif (isset($_POST['delete_form'])) {
        $stmt = $db->prepare('DELETE FROM form_submissions WHERE id = ?');
        $stmt->execute([$id]);
        
        header('Location: forms.php?message=Formularz+usunięty');
        exit;
    }
}

// Pobierz listę formularzy
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = 'SELECT * FROM form_submissions WHERE 1=1';
$params = [];

if ($search) {
    $sql .= ' AND (email LIKE ? OR full_name LIKE ? OR company LIKE ?)';
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($type) {
    $sql .= ' AND form_type = ?';
    $params[] = $type;
}

if ($statusFilter) {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $sql .= ' AND DATE(created_at) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= ' AND DATE(created_at) <= ?';
    $params[] = $dateTo;
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statystyki formularzy
$stmt = $db->query('SELECT form_type, status, COUNT(*) as count 
                    FROM form_submissions 
                    GROUP BY form_type, status');
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$formStats = [
    'total' => count($forms),
    'new' => 0,
    'contacted' => 0,
    'converted' => 0
];

foreach ($forms as $form) {
    if ($form['status'] === 'new') $formStats['new']++;
    if ($form['status'] === 'contacted') $formStats['contacted']++;
    if ($form['status'] === 'converted') $formStats['converted']++;
}

// Pobierz pojedynczy formularz do podglądu
if ($action === 'view' && $id) {
    $stmt = $db->prepare('SELECT * FROM form_submissions WHERE id = ?');
    $stmt->execute([$id]);
    $formDetail = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$formDetail) {
        header('Location: forms.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formularze - FlowQuest Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .form-card { border-left: 4px solid; }
        .form-contact { border-left-color: #667eea; }
        .form-demo { border-left-color: #28a745; }
        .form-newsletter { border-left-color: #ffc107; }
        .form-admin { border-left-color: #6c757d; }
        .badge-type { font-size: 0.7rem; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9">
                <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= h($_GET['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($action === 'list'): ?>
                <!-- Lista formularzy -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Formularze</h5>
                        <p class="text-muted small mb-0">Wszystkie formularze są przekierowywane do CEMR. Tutaj tylko log i statystyki.</p>
                    </div>
                    <div class="card-body">
                        <!-- Statystyki -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body py-3">
                                        <h6 class="card-title">Wszystkie</h6>
                                        <h3 class="mb-0"><?= $formStats['total'] ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body py-3">
                                        <h6 class="card-title">Nowe</h6>
                                        <h3 class="mb-0"><?= $formStats['new'] ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body py-3">
                                        <h6 class="card-title">Kontaktowane</h6>
                                        <h3 class="mb-0"><?= $formStats['contacted'] ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body py-3">
                                        <h6 class="card-title">Przekonwertowane</h6>
                                        <h3 class="mb-0"><?= $formStats['converted'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filtry -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Szukaj (email, nazwa)" value="<?= h($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="type" class="form-select">
                                    <option value="">Wszystkie typy</option>
                                    <option value="contact" <?= $type === 'contact' ? 'selected' : '' ?>>Kontakt</option>
                                    <option value="demo" <?= $type === 'demo' ? 'selected' : '' ?>>Demo</option>
                                    <option value="newsletter" <?= $type === 'newsletter' ? 'selected' : '' ?>>Newsletter</option>
                                    <option value="admin_login" <?= $type === 'admin_login' ? 'selected' : '' ?>>Logowania admin</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Wszystkie statusy</option>
                                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Nowy</option>
                                    <option value="contacted" <?= $statusFilter === 'contacted' ? 'selected' : '' ?>>Kontaktowany</option>
                                    <option value="converted" <?= $statusFilter === 'converted' ? 'selected' : '' ?>>Przekonwertowany</option>
                                    <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archiwalny</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?= h($dateFrom) ?>" placeholder="Od daty">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?= h($dateTo) ?>" placeholder="Do daty">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-filter"></i>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Tabela formularzy -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Typ</th>
                                        <th>Dane</th>
                                        <th>Status</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($forms)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Brak formularzy.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($forms as $form): ?>
                                    <tr>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($form['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $typeColors = [
                                                'contact' => 'primary',
                                                'demo' => 'success', 
                                                'newsletter' => 'warning',
                                                'admin_login' => 'secondary',
                                                'admin_login_failed' => 'danger',
                                                'admin_logout' => 'info'
                                            ];
                                            $color = $typeColors[$form['form_type']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?> badge-type">
                                                <?= h($form['form_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= h($form['full_name'] ?: $form['email']) ?></strong>
                                                <?php if ($form['company']): ?><br>
                                                <small><?= h($form['company']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusColors = [
                                                'new' => 'warning',
                                                'contacted' => 'info',
                                                'converted' => 'success',
                                                'archived' => 'secondary'
                                            ];
                                            $statusColor = $statusColors[$form['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $statusColor ?>">
                                                <?= h($form['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="forms.php?action=view&id=<?= $form['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Szczegóły">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form method="POST" action="forms.php?action=view&id=<?= $form['id'] ?>" 
                                                  class="d-inline" onsubmit="return confirm('Usunąć ten formularz?');">
                                                <input type="hidden" name="delete_form" value="1">
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
                
                <?php elseif ($action === 'view' && $formDetail): ?>
                <!-- Szczegóły formularza -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Szczegóły formularza</h5>
                        <a href="forms.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Powrót
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Dane formularza</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">Typ:</th>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= h($formDetail['form_type']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Data:</th>
                                        <td><?= date('d.m.Y H:i:s', strtotime($formDetail['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Imię i nazwisko:</th>
                                        <td><?= h($formDetail['full_name']) ?: '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?= h($formDetail['email']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Telefon:</th>
                                        <td><?= h($formDetail['phone']) ?: '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Firma:</th>
                                        <td><?= h($formDetail['company']) ?: '-' ?></td>
                                    </tr>
                                    <tr>
                                        <th>IP:</th>
                                        <td><?= h($formDetail['ip_address']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>URL źródłowy:</th>
                                        <td><?= h($formDetail['source_url']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Wiadomość</h6>
                                <div class="border rounded p-3 mb-3 bg-light" style="min-height: 150px;">
                                    <?= nl2br(h($formDetail['message'] ?: 'Brak wiadomości')) ?>
                                </div>
                                
                                <h6>User Agent</h6>
                                <div class="border rounded p-3 mb-3 bg-light small">
                                    <?= h($formDetail['user_agent']) ?>
                                </div>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="new" <?= $formDetail['status'] === 'new' ? 'selected' : '' ?>>Nowy</option>
                                            <option value="contacted" <?= $formDetail['status'] === 'contacted' ? 'selected' : '' ?>>Kontaktowany</option>
                                            <option value="converted" <?= $formDetail['status'] === 'converted' ? 'selected' : '' ?>>Przekonwertowany</option>
                                            <option value="archived" <?= $formDetail['status'] === 'archived' ? 'selected' : '' ?>>Archiwalny</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notatki</label>
                                        <textarea name="notes" class="form-control" rows="3"><?= h($formDetail['notes'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="update_status" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Zapisz zmiany
                                        </button>
                                        
                                        <form method="POST" onsubmit="return confirm('Na pewno usunąć?');">
                                            <input type="hidden" name="delete_form" value="1">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="bi bi-trash"></i> Usuń formularz
                                            </button>
                                        </form>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>