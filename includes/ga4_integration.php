<?php
/**
 * FlowQuest - Google Analytics 4 Integration
 * 
 * Funkcje do integracji z Google Analytics 4 API:
 * 1. Pobieranie danych analitycznych
 * 2. Synchronizacja z lokalną bazą
 * 3. Raporty w czasie rzeczywistym
 * 4. Śledzenie konwersji z artykułów
 */

// Konfiguracja GA4
define('GA4_API_URL', 'https://analyticsdata.googleapis.com/v1beta');
define('GA4_REALTIME_URL', 'https://analyticsdata.googleapis.com/v1beta/properties/{propertyId}:runRealtimeReport');

// Klasa do integracji z GA4 API
class GoogleAnalytics4 {
    private $propertyId;
    private $credentials;
    private $accessToken;
    private $db;
    
    public function __construct($propertyId = null, $credentialsPath = null) {
        global $config;
        
        $this->propertyId = $propertyId ?: getSetting('ga4_property_id', '');
        $this->credentials = $credentialsPath ?: DATA_DIR . '/ga4-credentials.json';
        $this->db = getDB();
        
        // Inicjalizuj token dostępu
        $this->refreshAccessToken();
    }
    
    /**
     * Odśwież token dostępu do GA4 API
     */
    private function refreshAccessToken() {
        $tokenFile = DATA_DIR . '/ga4-token.json';
        
        // Sprawdź czy token jest jeszcze ważny
        if (file_exists($tokenFile)) {
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            $expiresAt = $tokenData['expires_at'] ?? 0;
            
            if ($expiresAt > time() + 300) { // 5 minut zapasu
                $this->accessToken = $tokenData['access_token'];
                return;
            }
        }
        
        // Pobierz nowy token
        $this->fetchNewAccessToken();
    }
    
    /**
     * Pobierz nowy token dostępu
     */
    private function fetchNewAccessToken() {
        // W praktyce: użyj OAuth 2.0 do pobrania tokena
        // Tutaj uproszczona wersja - wymaga skonfigurowanych credentials
        
        $tokenFile = DATA_DIR . '/ga4-token.json';
        $credentialsFile = $this->credentials;
        
        if (!file_exists($credentialsFile)) {
            error_log('GA4: Brak pliku z credentials');
            return false;
        }
        
        // W rzeczywistej implementacji tutaj byłaby logika OAuth 2.0
        // Dla uproszczenia - symulacja
        $tokenData = [
            'access_token' => 'simulated_token_' . md5(time()),
            'expires_in' => 3600,
            'expires_at' => time() + 3600,
            'token_type' => 'Bearer'
        ];
        
        file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
        $this->accessToken = $tokenData['access_token'];
        
        return true;
    }
    
    /**
     * Wyślij żądanie do GA4 API
     */
    private function makeApiRequest($endpoint, $data = []) {
        if (!$this->accessToken) {
            $this->refreshAccessToken();
        }
        
        $url = GA4_API_URL . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => !empty($data),
            CURLOPT_POSTFIELDS => !empty($data) ? json_encode($data) : null,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("GA4 API Error ($httpCode): " . $response);
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Pobierz raport z GA4
     */
    public function getReport($dimensions = [], $metrics = [], $dateRanges = [], $filters = []) {
        $requestData = [
            'dimensions' => array_map(function($dim) {
                return ['name' => $dim];
            }, $dimensions),
            'metrics' => array_map(function($met) {
                return ['name' => $met];
            }, $metrics),
            'dateRanges' => !empty($dateRanges) ? $dateRanges : [
                ['startDate' => '30daysAgo', 'endDate' => 'today']
            ]
        ];
        
        if (!empty($filters)) {
            $requestData['dimensionFilter'] = $filters;
        }
        
        $endpoint = "/properties/{$this->propertyId}:runReport";
        return $this->makeApiRequest($endpoint, $requestData);
    }
    
    /**
     * Pobierz statystyki artykułów
     */
    public function getArticleAnalytics($articleSlug = null, $days = 30) {
        $dimensions = ['pagePath', 'pageTitle'];
        $metrics = ['screenPageViews', 'userEngagementDuration', 'bounceRate', 'conversions'];
        
        $filters = [
            'filter' => [
                'fieldName' => 'pagePath',
                'stringFilter' => [
                    'matchType' => 'CONTAINS',
                    'value' => '/blog/'
                ]
            ]
        ];
        
        if ($articleSlug) {
            $filters['filter']['stringFilter']['value'] = $articleSlug;
        }
        
        $dateRanges = [
            ['startDate' => "{$days}daysAgo", 'endDate' => 'today']
        ];
        
        return $this->getReport($dimensions, $metrics, $dateRanges, $filters);
    }
    
    /**
     * Pobierz źródła ruchu
     */
    public function getTrafficSources($days = 30) {
        $dimensions = ['sessionSource', 'sessionMedium'];
        $metrics = ['sessions', 'users', 'newUsers', 'bounceRate', 'conversions'];
        
        return $this->getReport($dimensions, $metrics, [
            ['startDate' => "{$days}daysAgo", 'endDate' => 'today']
        ]);
    }
    
    /**
     * Pobierz dane w czasie rzeczywistym
     */
    public function getRealtimeData() {
        $dimensions = ['pagePath', 'pageTitle'];
        $metrics = ['activeUsers'];
        
        $requestData = [
            'dimensions' => array_map(function($dim) {
                return ['name' => $dim];
            }, $dimensions),
            'metrics' => array_map(function($met) {
                return ['name' => $met];
            }, $metrics)
        ];
        
        $endpoint = "/properties/{$this->propertyId}:runRealtimeReport";
        return $this->makeApiRequest($endpoint, $requestData);
    }
    
    /**
     * Synchronizuj dane z GA4 do lokalnej bazy
     */
    public function syncToDatabase($days = 7) {
        try {
            // 1. Pobierz statystyki artykułów
            $articlesData = $this->getArticleAnalytics(null, $days);
            
            if (!$articlesData || !isset($articlesData['rows'])) {
                return ['success' => false, 'message' => 'Brak danych z GA4'];
            }
            
            $processed = 0;
            $errors = 0;
            
            foreach ($articlesData['rows'] as $row) {
                $dimensionValues = $row['dimensionValues'] ?? [];
                $metricValues = $row['metricValues'] ?? [];
                
                if (count($dimensionValues) < 2) continue;
                
                $pagePath = $dimensionValues[0]['value'] ?? '';
                $pageTitle = $dimensionValues[1]['value'] ?? '';
                
                // Znajdź artykuł po ścieżce lub tytule
                $articleId = $this->findArticleId($pagePath, $pageTitle);
                
                if (!$articleId) continue;
                
                // Zapisz do bazy
                $success = $this->saveArticleStats(
                    $articleId,
                    $metricValues[0]['value'] ?? 0, // pageviews
                    $metricValues[1]['value'] ?? 0, // engagement duration
                    $metricValues[2]['value'] ?? 0, // bounce rate
                    $metricValues[3]['value'] ?? 0, // conversions
                    date('Y-m-d')
                );
                
                $success ? $processed++ : $errors++;
            }
            
            // 2. Synchronizuj log
            $this->logSync('articles', $processed, $errors);
            
            return [
                'success' => true,
                'processed' => $processed,
                'errors' => $errors,
                'message' => "Zsynchonizowano $processed artykułów ($errors błędów)"
            ];
            
        } catch (Exception $e) {
            error_log('GA4 Sync Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Znajdź ID artykułu na podstawie ścieżki lub tytułu
     */
    private function findArticleId($pagePath, $pageTitle) {
        // Wyciągnij slug ze ścieżki
        $slug = '';
        if (preg_match('/\/([^\/]+)\.html/', $pagePath, $matches)) {
            $slug = $matches[1];
        } elseif (preg_match('/article=([^&]+)/', $pagePath, $matches)) {
            $slug = $matches[1];
        }
        
        if ($slug) {
            $stmt = $this->db->prepare('SELECT id FROM articles WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
        }
        
        // Spróbuj po tytule
        $title = html_entity_decode($pageTitle, ENT_QUOTES, 'UTF-8');
        $stmt = $this->db->prepare('SELECT id FROM articles WHERE title_pl LIKE ? LIMIT 1');
        $stmt->execute(["%{$title}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
    }
    
    /**
     * Zapisz statystyki artykułu do bazy
     */
    private function saveArticleStats($articleId, $pageviews, $engagement, $bounceRate, $conversions, $date) {
        try {
            // Sprawdź czy już istnieje wpis na ten dzień
            $stmt = $this->db->prepare('SELECT id FROM article_performance WHERE article_id = ? AND date = ?');
            $stmt->execute([$articleId, $date]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Aktualizuj istniejący
                $stmt = $this->db->prepare('UPDATE article_performance SET 
                    pageviews = pageviews + ?, 
                    avg_time_on_page = ((avg_time_on_page * pageviews) + ?) / (pageviews + ?),
                    bounce_rate = ((bounce_rate * pageviews) + ?) / (pageviews + ?),
                    demo_requests = demo_requests + ?
                    WHERE article_id = ? AND date = ?');
                
                $stmt->execute([
                    $pageviews,
                    $engagement,
                    $pageviews,
                    $bounceRate,
                    $pageviews,
                    $conversions,
                    $articleId,
                    $date
                ]);
            } else {
                // Wstaw nowy
                $stmt = $this->db->prepare('INSERT INTO article_performance 
                    (article_id, date, pageviews, avg_time_on_page, bounce_rate, demo_requests) 
                    VALUES (?, ?, ?, ?, ?, ?)');
                
                $stmt->execute([
                    $articleId,
                    $date,
                    $pageviews,
                    $engagement,
                    $bounceRate,
                    $conversions
                ]);
            }
            
            // Aktualizuj ogólne statystyki artykułu
            $this->updateArticlePerformanceScore($articleId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Save Article Stats Error: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Aktualizuj ogólny wynik wydajności artykułu
     */
    private function updateArticlePerformanceScore($articleId) {
        $stmt = $this->db->prepare('
            UPDATE articles SET 
                views_count = COALESCE((SELECT SUM(pageviews) FROM article_performance WHERE article_id = ?), 0),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $stmt->execute([$articleId, $articleId]);
    }
    
    /**
     * Zapisz log synchronizacji
     */
    private function logSync($syncType, $fetched, $processed, $status = 'success') {
        $stmt = $this->db->prepare('
            INSERT INTO ga_sync_log (sync_date, sync_type, records_fetched, records_processed, sync_status)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            date('Y-m-d'),
            $syncType,
            $fetched,
            $processed,
            $status
        ]);
    }
    
    /**
     * Generuj tag GA4 do wstawienia w head
     */
    public static function generateTrackingCode($measurementId = null) {
        $measurementId = $measurementId ?: getSetting('ga4_measurement_id', 'G-XXXXXXXXXX');
        
        if (empty($measurementId) || $measurementId === 'G-XXXXXXXXXX') {
            return '<!-- GA4 disabled - no measurement ID -->';
        }
        
        return <<<HTML
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={$measurementId}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
        
          gtag('config', '{$measurementId}');
          
          // Track article reads
          document.addEventListener('DOMContentLoaded', function() {
            const articleSlug = window.location.pathname.match(/\\/([^\\/]+)\\.html/) || 
                               new URLSearchParams(window.location.search).get('article');
            
            if (articleSlug) {
              // Track time on page
              let startTime = Date.now();
              let maxScroll = 0;
              
              window.addEventListener('scroll', function() {
                const scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
                maxScroll = Math.max(maxScroll, scrollPercent);
              });
              
              window.addEventListener('beforeunload', function() {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                
                gtag('event', 'article_read', {
                  'article_slug': articleSlug,
                  'time_spent_seconds': timeSpent,
                  'scroll_depth': Math.round(maxScroll),
                  'page_location': window.location.href
                });
              });
              
              // Track conversions
              document.querySelectorAll('a[href*="cemr.flowquest.pl/demo"]').forEach(function(link) {
                link.addEventListener('click', function() {
                  gtag('event', 'demo_request', {
                    'article_slug': articleSlug,
                    'link_text': this.textContent.trim(),
                    'page_location': window.location.href
                  });
                });
              });
            }
          });
        </script>
HTML;
    }
}

// Funkcje pomocnicze dla frontendu

/**
 * Pobierz statystyki artykułu
 */
function getArticleStats($articleId, $days = 30) {
    $db = getDB();
    
    $stmt = $db->prepare('
        SELECT 
            SUM(pageviews) as total_views,
            AVG(avg_time_on_page) as avg_time,
            AVG(bounce_rate) as bounce_rate,
            SUM(demo_requests) as demo_requests,
            COUNT(DISTINCT date) as days_tracked
        FROM article_performance 
        WHERE article_id = ? AND date >= date("now", ?)
    ');
    
    $stmt->execute([$articleId, "-{$days} days"]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pobierz cytowania AI
    $stmt = $db->prepare('SELECT COUNT(*) as ai_citations FROM ai_citation_log WHERE article_id = ?');
    $stmt->execute([$articleId]);
    $aiStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['ai_citations'] = $aiStats['ai_citations'] ?? 0;
    
    return $stats;
}

/**
 * Pobierz porównanie artykułów
 */
function compareArticles($articleIds = [], $days = 30) {
    if (empty($articleIds)) {
        // Pobierz wszystkie artykuły
        $db = getDB();
        $stmt = $db->query('SELECT id FROM articles WHERE status = "published" ORDER BY created_at DESC LIMIT 10');
        $articleIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
    
    $comparison = [];
    
    foreach ($articleIds as $articleId) {
        $stats = getArticleStats($articleId, $days);
        
        $db = getDB();
        $stmt = $db->prepare('SELECT title_pl, slug, category_id FROM articles WHERE id = ?');
        $stmt->execute([$articleId]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Oblicz score
        $score = calculateArticleScore($stats);
        
        $comparison[] = [
            'id' => $articleId,
            'title' => $article['title_pl'] ?? '',
            'slug' => $article['slug'] ?? '',
            'stats' => $stats,
            'score' => $score,
            'category_id' => $article['category_id'] ?? null
        ];
    }
    
    // Sortuj według score
    usort($comparison, function($a, $b) {
        return $b['score']['total'] <=> $a['score']['total'];
    });
    
    return $comparison;
}

/**
 * Oblicz wynik artykułu
 */
function calculateArticleScore($stats) {
    $totalViews = $stats['total_views'] ?? 0;
    $avgTime = $stats['avg_time'] ?? 0;
    $bounceRate = $stats['bounce_rate'] ?? 100;
    $demoRequests = $stats['demo_requests'] ?? 0;
    $aiCitations = $stats['ai_citations'] ?? 0;
    
    // Wagi (modyfikowalne)
    $weights = [
        'views' => 0.3,
        'engagement' => 0.25,
        'conversion' => 0.3,
        'ai' => 0.15
    ];
    
    // Normalizacja (0-100)
    $viewScore = min(100, ($totalViews / 1000) * 100); // 1000 views = 100 pkt
    $engagementScore = min(100, ($avgTime / 180) * 100); // 3 minuty = 100 pkt
    $bounceScore = max(0, 100 - $bounceRate); // im niższy bounce, tym lepiej
    
    $conversionRate = $totalViews > 0 ? ($demoRequests / $totalViews) * 100 : 0;
    $conversionScore = min(100, $conversionRate * 100); // 1% konwersji = 100 pkt
    
    $aiScore = min(100, $aiCitations * 20); // 5 cytowań = 100 pkt
    
    // Połączone score
    $engagementCombined = ($engagementScore * 0.6) + ($bounceScore * 0.4);
    
    $totalScore = (
        $viewScore * $weights['views'] +
        $engagementCombined * $weights['engagement'] +
        $conversionScore * $weights['conversion'] +
        $aiScore * $weights['ai']
    );
    
    return [
        'total' => round($totalScore, 1),
        'components' => [
            'views' => round($viewScore, 1),
            'engagement' => round($engagementCombined, 1),
            'conversion' => round($conversionScore, 1),
            'ai' => round($aiScore, 1)
        ],
        'raw' => [
            'views' => $totalViews,
            'avg_time' => round($avgTime, 1),
            'bounce_rate' => round($bounceRate, 1),
            'conversion_rate' => round($conversionRate, 2),
            'ai_citations' => $aiCitations
        ]
    ];
}

/**
 * Pobierz najlepsze artykuły według kategorii
 */
function getTopArticlesByCategory($days = 30, $limit = 5) {
    $db = getDB();
    
    $stmt = $db->prepare('
        SELECT 
            c.id as category_id,
            c.name_pl as category_name,
            a.id as article_id,
            a.title_pl,
            a.slug,
            COALESCE(SUM(ap.pageviews), 0) as total_views,
            COALESCE(AVG(ap.avg_time_on_page), 0) as avg_time,
            COALESCE(SUM(ap.demo_requests), 0) as demo_requests,
            COUNT(acl.id) as ai_citations
        FROM categories c
        LEFT JOIN articles a ON a.category_id = c.id AND a.status = "published"
        LEFT JOIN article_performance ap ON ap.article_id = a.id AND ap.date >= date("now", ?)
        LEFT JOIN ai_citation_log acl ON acl.article_id = a.id
        GROUP BY c.id, a.id
        HAVING total_views > 0
        ORDER BY c.sort_order, total_views DESC
    ');
    
    $stmt->execute(["-{$days} days"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grupuj według kategorii
    $grouped = [];
    foreach ($results as $row) {
        $categoryId = $row['category_id'];
        if (!isset($grouped[$categoryId])) {
            $grouped[$categoryId] = [
                'category_name' => $row['category_name'],
                'articles' => []
            ];
        }
        
        if (count($grouped[$categoryId]['articles']) < $limit) {
            $grouped[$categoryId]['articles'][] = $row;
        }
    }
    
    return $grouped;
}

/**
 * Pobierz trendy (wzrost/spadek)
 */
function getArticleTrends($articleId, $compareDays = 7) {
    $db = getDB();
    
    $stmt = $db->prepare('
        SELECT 
            date,
            pageviews,
            avg_time_on_page,
            bounce_rate,
            demo_requests
        FROM article_performance 
        WHERE article_id = ? AND date >= date("now", ?)
        ORDER BY date DESC
        LIMIT 14
    ');
    
    $stmt->execute([$articleId, "-{$compareDays} days"]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recent) < 2) {
        return ['trend' => 'stable', 'change' => 0];
    }
    
    $current = $recent[0];
    $previous = $recent[1] ?? $recent[0];
    
    $changeViews = $previous['pageviews'] > 0 
        ? (($current['pageviews'] - $previous['pageviews']) / $previous['pageviews']) * 100 
        : ($current['pageviews'] > 0 ? 100 : 0);
    
    $changeTime = $previous['avg_time_on_page'] > 0
        ? (($current['avg_time_on_page'] - $previous['avg_time_on_page']) / $previous['avg_time_on_page']) * 100
        : 0;
    
    $changeBounce = $previous['bounce_rate'] > 0
        ? (($current['bounce_rate'] - $previous['bounce_rate']) / $previous['bounce_rate']) * 100
        : 0;
    
    // Określ trend
    $score = ($changeViews * 0.4) + ($changeTime * 0.3) - ($changeBounce * 0.3);
    
    if ($score > 20) {
        $trend = 'up';
    } elseif ($score < -20) {
        $trend = 'down';
    } else {
        $trend = 'stable';
    }
    
    return [
        'trend' => $trend,
        'change' => round($score, 1),
        'details' => [
            'views_change' => round($changeViews, 1),
            'time_change' => round($changeTime, 1),
            'bounce_change' => round($changeBounce, 1)
        ],
        'current' => $current,
        'previous' => $previous
    ];
}

// Inicjalizacja GA4 (opcjonalna)
function initGA4() {
    static $ga4 = null;
    
    if ($ga4 === null) {
        $propertyId = getSetting('ga4_property_id', '');
        if (!empty($propertyId) && $propertyId !== 'G-XXXXXXXXXX') {
            $ga4 = new GoogleAnalytics4($propertyId);
        }
    }
    
    return $ga4;
}