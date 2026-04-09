<?php
// Sprawdź aktualną stronę dla zaznaczenia aktywnego menu
$currentFile = basename($_SERVER['PHP_SELF']);
$activePage = $currentFile;
?>
<!-- Sidebar -->
<div class="col-md-3 col-lg-2 sidebar p-0 bg-dark">
    <div class="p-3 text-center bg-primary text-white">
        <h5 class="mb-0">FlowQuest</h5>
        <p class="small mb-0">Panel Administracyjny</p>
    </div>
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a class="nav-link <?= $activePage === 'index.php' ? 'active bg-primary text-white' : 'text-white' ?>" 
               href="index.php">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activePage === 'articles.php' ? 'active bg-primary text-white' : 'text-white' ?>" 
               href="articles.php">
                <i class="bi bi-file-earmark-text me-2"></i> Artykuły
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activePage === 'categories.php' ? 'active bg-primary text-white' : 'text-white' ?>" 
               href="categories.php">
                <i class="bi bi-tags me-2"></i> Kategorie
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activePage === 'forms.php' ? 'active bg-primary text-white' : 'text-white' ?>" 
               href="forms.php">
                <i class="bi bi-envelope me-2"></i> Formularze
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activePage === 'settings.php' ? 'active bg-primary text-white' : 'text-white' ?>" 
               href="settings.php">
                <i class="bi bi-gear me-2"></i> Ustawienia
            </a>
        </li>
        <li class="nav-item mt-4 border-top pt-3">
            <a class="nav-link text-white" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> Wyloguj się
            </a>
        </li>
    </ul>
    
    <div class="position-absolute bottom-0 start-0 end-0 p-3 text-center text-white-50 small border-top">
        <div>© <?= date('Y') ?> FlowQuest</div>
        <div class="mt-1">Wersja 1.0</div>
        <div class="mt-1">
            <?php
            $db = getDB();
            $stmt = $db->query('SELECT COUNT(*) as count FROM articles WHERE status = "published"');
            $published = $stmt->fetch()['count'] ?? 0;
            ?>
            <span class="badge bg-success"><?= $published ?> artykułów</span>
        </div>
    </div>
</div>