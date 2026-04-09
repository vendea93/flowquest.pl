<?php
// Sprawdź autoryzację w każdym pliku który include'uje ten header
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<header class="header p-3 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">
                <?php 
                $pageTitle = '';
                $currentFile = basename($_SERVER['PHP_SELF']);
                
                switch($currentFile) {
                    case 'index.php': $pageTitle = 'Dashboard'; break;
                    case 'articles.php': $pageTitle = 'Artykuły'; break;
                    case 'categories.php': $pageTitle = 'Kategorie'; break;
                    case 'forms.php': $pageTitle = 'Formularze'; break;
                    case 'settings.php': $pageTitle = 'Ustawienia'; break;
                    default: $pageTitle = 'Admin';
                }
                
                echo $pageTitle;
                ?>
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $pageTitle ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted">
                Witaj, <strong><?= h($_SESSION['admin_username'] ?? 'Admin') ?></strong>
            </span>
            <div class="vr"></div>
            <span class="text-muted">
                <?= date('d.m.Y H:i') ?>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Wyloguj
            </a>
        </div>
    </div>
</header>