<?php
/**
 * FlowQuest Admin API — file-based articles management
 * No MySQL required. Stores articles in /data/articles.json
 */

// Very simple password protection — change this password!
define('ADMIN_PASSWORD', 'flowquest2026');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Auth check
$token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($token !== ADMIN_PASSWORD) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dataFile = __DIR__ . '/../data/articles.json';

function loadArticles(string $file): array {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    return json_decode($json, true) ?? [];
}

function saveArticles(string $file, array $articles): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return (bool) file_put_contents($file, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $articles = loadArticles($dataFile);
        // Only return published articles for list, all for admin
        $forAdmin = ($_GET['admin'] ?? '') === '1';
        if (!$forAdmin) {
            $articles = array_values(array_filter($articles, fn($a) => $a['published'] ?? false));
        }
        // Sort by date desc
        usort($articles, fn($a, $b) => strcmp($b['date'], $a['date']));
        echo json_encode(['success' => true, 'articles' => $articles]);
        break;

    case 'POST':
        $articles = loadArticles($dataFile);
        $article  = $input;

        if (empty($article['id'])) {
            // New article
            $article['id']      = (string) (time());
            $article['date']    = date('Y-m-d');
            $article['published'] = (bool)($article['published'] ?? false);
            $articles[] = $article;
        } else {
            // Update existing
            $found = false;
            foreach ($articles as &$a) {
                if ($a['id'] === $article['id']) {
                    $article['published'] = (bool)($article['published'] ?? false);
                    $a = $article;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $article['date']    = date('Y-m-d');
                $article['published'] = (bool)($article['published'] ?? false);
                $articles[] = $article;
            }
        }

        if (saveArticles($dataFile, $articles)) {
            echo json_encode(['success' => true, 'article' => $article]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Nie można zapisać pliku.']);
        }
        break;

    case 'DELETE':
        $id       = $_GET['id'] ?? '';
        $articles = loadArticles($dataFile);
        $articles = array_values(array_filter($articles, fn($a) => $a['id'] !== $id));
        if (saveArticles($dataFile, $articles)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Nie można zapisać pliku.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
