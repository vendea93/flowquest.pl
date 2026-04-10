<?php
require_once '../includes/config.php';

// Zapis log wylogowania
if (isset($_SESSION['admin_username'])) {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO form_submissions (form_type, email, source_url, ip_address, user_agent, notes) 
                         VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        'admin_logout',
        $_SESSION['admin_username'],
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        'Admin logout'
    ]);
}

// Zniszcz sesję
$_SESSION = array();

// Usuń cookie sesji
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Przekieruj do logowania
header('Location: login.php');
exit;
?>