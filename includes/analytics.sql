-- FlowQuest - SYSTEM ANALITYKI I SEO
-- Tabele dla Google Analytics, statystyk artykułów i optymalizacji AI

-- 1. STATYSTYKI STRONY (Google Analytics + własne)
CREATE TABLE IF NOT EXISTS page_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_url TEXT NOT NULL,
    page_title TEXT,
    page_type TEXT, -- 'article', 'home', 'contact', 'about', etc.
    article_id INTEGER,
    
    -- Statystyki sesji
    session_id TEXT,
    user_id TEXT,
    ip_address TEXT,
    user_agent TEXT,
    referrer TEXT,
    referrer_domain TEXT,
    device_type TEXT, -- 'desktop', 'mobile', 'tablet'
    browser TEXT,
    os TEXT,
    country TEXT,
    city TEXT,
    
    -- Metryki
    pageviews INTEGER DEFAULT 1,
    unique_pageviews INTEGER DEFAULT 1,
    time_on_page INTEGER, -- w sekundach
    bounce_rate REAL DEFAULT 0,
    exit_rate REAL DEFAULT 0,
    
    -- Źródła ruchu
    traffic_source TEXT, -- 'organic', 'direct', 'social', 'referral', 'email'
    traffic_medium TEXT, -- 'organic', 'cpc', 'social', 'email', 'referral'
    campaign_name TEXT,
    campaign_source TEXT,
    campaign_medium TEXT,
    campaign_content TEXT,
    campaign_term TEXT,
    
    -- Konwersje
    conversion_type TEXT, -- 'demo_request', 'contact', 'newsletter', 'download'
    conversion_value REAL DEFAULT 0,
    
    -- Daty
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indeksy
    INDEX idx_page_url (page_url),
    INDEX idx_article_id (article_id),
    INDEX idx_visit_date (visit_date),
    INDEX idx_traffic_source (traffic_source),
    INDEX idx_conversion (conversion_type)
);

-- 2. WYDAJNOŚĆ ARTYKUŁÓW (AI SEO OPTIMIZATION)
CREATE TABLE IF NOT EXISTS article_performance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    date DATE NOT NULL,
    
    -- Metryki zaangażowania
    pageviews INTEGER DEFAULT 0,
    unique_visitors INTEGER DEFAULT 0,
    avg_time_on_page INTEGER DEFAULT 0, -- w sekundach
    scroll_depth REAL DEFAULT 0, -- % przewiniętej strony
    bounce_rate REAL DEFAULT 0,
    
    -- Konwersje z artykułu
    demo_requests INTEGER DEFAULT 0,
    contact_forms INTEGER DEFAULT 0,
    newsletter_signups INTEGER DEFAULT 0,
    
    -- SEO metryki
    organic_views INTEGER DEFAULT 0,
    social_shares INTEGER DEFAULT 0,
    backlinks INTEGER DEFAULT 0,
    
    -- AI/ML metryki
    ai_citations INTEGER DEFAULT 0, -- ile razy artykuł był cytowany przez AI
    featured_snippets INTEGER DEFAULT 0,
    ranking_position REAL DEFAULT 0, -- średnia pozycja w Google
    
    -- Frazy kluczowe
    primary_keyword TEXT,
    secondary_keywords TEXT, -- JSON array
    
    -- Ocena jakości
    quality_score REAL DEFAULT 0, -- 0-100
    engagement_score REAL DEFAULT 0,
    conversion_score REAL DEFAULT 0,
    seo_score REAL DEFAULT 0,
    
    UNIQUE(article_id, date),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    
    -- Indeksy
    INDEX idx_article_date (article_id, date),
    INDEX idx_performance (quality_score DESC)
);

-- 3. FRAZY KLUCZOWE I POZYCJONOWANIE
CREATE TABLE IF NOT EXISTS keyword_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword TEXT NOT NULL,
    article_id INTEGER,
    category_id INTEGER,
    
    -- Pozycjonowanie
    current_position INTEGER DEFAULT 100, -- 1-100, 100 = nie w top 100
    previous_position INTEGER DEFAULT 100,
    position_change INTEGER DEFAULT 0,
    search_volume INTEGER DEFAULT 0,
    competition_level TEXT, -- 'low', 'medium', 'high'
    cpc REAL DEFAULT 0, -- koszt per click
    
    -- Trendy
    trend_7d INTEGER DEFAULT 0,
    trend_30d INTEGER DEFAULT 0,
    
    -- Daty
    last_checked DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    
    -- Indeksy
    INDEX idx_keyword (keyword),
    INDEX idx_article_keyword (article_id, keyword),
    UNIQUE(keyword, article_id)
);

-- 4. ŹRÓDŁA RUCHU (ANALITYKA)
CREATE TABLE IF NOT EXISTS traffic_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date DATE NOT NULL,
    source_type TEXT NOT NULL, -- 'google', 'facebook', 'linkedin', 'direct', 'referral'
    medium TEXT NOT NULL, -- 'organic', 'cpc', 'social', 'email'
    
    -- Metryki
    sessions INTEGER DEFAULT 0,
    users INTEGER DEFAULT 0,
    new_users INTEGER DEFAULT 0,
    pageviews INTEGER DEFAULT 0,
    avg_session_duration INTEGER DEFAULT 0,
    bounce_rate REAL DEFAULT 0,
    
    -- Konwersje
    goal_completions INTEGER DEFAULT 0,
    goal_value REAL DEFAULT 0,
    conversion_rate REAL DEFAULT 0,
    
    -- Koszty (dla płatnych źródeł)
    cost REAL DEFAULT 0,
    cpc REAL DEFAULT 0,
    roas REAL DEFAULT 0, -- Return on Ad Spend
    
    UNIQUE(date, source_type, medium),
    
    -- Indeksy
    INDEX idx_date_source (date, source_type)
);

-- 5. REKOMENDACJE AI DLA ARTYKUŁÓW
CREATE TABLE IF NOT EXISTS ai_recommendations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    analysis_date DATE NOT NULL,
    
    -- Analiza treści
    readability_score REAL DEFAULT 0, -- 0-100
    seo_score REAL DEFAULT 0,
    ai_friendliness_score REAL DEFAULT 0, -- jak przyjazny dla modeli AI
    
    -- Sugestie AI
    title_suggestions TEXT, -- JSON array
    meta_description_suggestions TEXT, -- JSON array
    heading_suggestions TEXT, -- JSON array
    content_gaps TEXT, -- JSON array
    keyword_opportunities TEXT, -- JSON array
    
    -- Współczynniki
    word_count INTEGER DEFAULT 0,
    heading_count INTEGER DEFAULT 0,
    link_count INTEGER DEFAULT 0,
    image_count INTEGER DEFAULT 0,
    
    -- Techniczne
    page_load_time REAL DEFAULT 0,
    mobile_friendly BOOLEAN DEFAULT 1,
    schema_markup_present BOOLEAN DEFAULT 0,
    
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    
    -- Indeksy
    INDEX idx_article_ai (article_id, analysis_date)
);

-- 6. KONWERSJE Z ARTYKUŁÓW
CREATE TABLE IF NOT EXISTS article_conversions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    conversion_type TEXT NOT NULL, -- 'demo', 'contact', 'newsletter', 'download'
    conversion_date DATE NOT NULL,
    conversion_time TIME NOT NULL,
    
    -- Dane użytkownika
    user_email TEXT,
    user_name TEXT,
    user_company TEXT,
    user_source TEXT, -- skąd przyszedł
    
    -- Wartość konwersji
    conversion_value REAL DEFAULT 0,
    lead_score INTEGER DEFAULT 0, -- 0-100
    
    -- Ścieżka konwersji
    previous_articles TEXT, -- JSON array of article IDs visited before conversion
    time_to_convert INTEGER, -- w sekundach od pierwszej wizyty
    touchpoints INTEGER DEFAULT 1, -- ilość interakcji przed konwersją
    
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    
    -- Indeksy
    INDEX idx_article_conversion (article_id, conversion_date),
    INDEX idx_conversion_type (conversion_type)
);

-- 7. DZIENNIK AKTYWNOŚCI AI (kiedy modele AI cytują artykuł)
CREATE TABLE IF NOT EXISTS ai_citation_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    citation_date DATE NOT NULL,
    
    -- Źródło AI
    ai_model TEXT, -- 'chatgpt', 'claude', 'gemini', 'perplexity', 'copilot'
    ai_source TEXT, -- 'chat.openai.com', 'claude.ai', etc.
    citation_context TEXT, -- kontekst cytowania
    
    -- Wpływ
    estimated_reach INTEGER DEFAULT 0, -- szacowany zasięg
    sentiment TEXT, -- 'positive', 'neutral', 'negative'
    citation_quality INTEGER DEFAULT 0, -- 0-100
    
    -- Link do cytowania
    citation_url TEXT,
    snippet TEXT, -- fragment cytowany
    
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    
    -- Indeksy
    INDEX idx_article_citation (article_id, citation_date),
    INDEX idx_ai_model (ai_model)
);

-- 8. DANE SYNCHRONIZACJI GOOGLE ANALYTICS
CREATE TABLE IF NOT EXISTS ga_sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sync_date DATE NOT NULL,
    sync_type TEXT NOT NULL, -- 'daily', 'realtime', 'articles', 'keywords'
    
    -- Statystyki synchronizacji
    records_fetched INTEGER DEFAULT 0,
    records_processed INTEGER DEFAULT 0,
    sync_duration INTEGER DEFAULT 0, -- w sekundach
    sync_status TEXT, -- 'success', 'partial', 'failed'
    
    -- Błędy
    error_message TEXT,
    last_successful_sync TIMESTAMP,
    
    -- Indeksy
    INDEX idx_sync_date (sync_date),
    INDEX idx_sync_type (sync_type)
);

-- WSTAW DANE POCZĄTKOWE
INSERT OR IGNORE INTO settings (key, value, category, type) VALUES
('ga4_measurement_id', 'G-XXXXXXXXXX', 'analytics', 'text'),
('ga4_api_secret', '', 'analytics', 'password'),
('ga4_property_id', '', 'analytics', 'text'),
('analytics_daily_sync', '1', 'analytics', 'boolean'),
('ai_optimization_enabled', '1', 'seo', 'boolean'),
('min_article_length', '1200', 'seo', 'number'),
('target_readability_score', '70', 'seo', 'number'),
('enable_ai_citation_tracking', '1', 'seo', 'boolean'),
('google_search_console_enabled', '0', 'seo', 'boolean'),
('google_search_console_property', '', 'seo', 'text');

-- UTWÓRZ WIDOKI DLA RAPORTÓW

-- Widok: Top artykuły według zaangażowania
CREATE VIEW IF NOT EXISTS v_top_articles AS
SELECT 
    a.id,
    a.title_pl,
    a.slug,
    c.name_pl as category_name,
    COALESCE(ap.pageviews, 0) as pageviews,
    COALESCE(ap.avg_time_on_page, 0) as avg_time_on_page,
    COALESCE(ap.bounce_rate, 0) as bounce_rate,
    COALESCE(ap.demo_requests, 0) as demo_requests,
    COALESCE(ap.ai_citations, 0) as ai_citations,
    COALESCE(ap.quality_score, 0) as quality_score,
    COALESCE(ap.engagement_score, 0) as engagement_score
FROM articles a
LEFT JOIN categories c ON a.category_id = c.id
LEFT JOIN (
    SELECT article_id, 
           SUM(pageviews) as pageviews,
           AVG(avg_time_on_page) as avg_time_on_page,
           AVG(bounce_rate) as bounce_rate,
           SUM(demo_requests) as demo_requests,
           SUM(ai_citations) as ai_citations,
           AVG(quality_score) as quality_score,
           AVG(engagement_score) as engagement_score
    FROM article_performance 
    WHERE date >= date('now', '-30 days')
    GROUP BY article_id
) ap ON a.id = ap.article_id
WHERE a.status = 'published'
ORDER BY COALESCE(ap.quality_score, 0) DESC, COALESCE(ap.pageviews, 0) DESC;

-- Widok: Źródła ruchu
CREATE VIEW IF NOT EXISTS v_traffic_sources AS
SELECT 
    source_type,
    medium,
    SUM(sessions) as total_sessions,
    SUM(users) as total_users,
    SUM(new_users) as total_new_users,
    AVG(bounce_rate) as avg_bounce_rate,
    SUM(goal_completions) as total_conversions,
    AVG(conversion_rate) as avg_conversion_rate
FROM traffic_sources 
WHERE date >= date('now', '-30 days')
GROUP BY source_type, medium
ORDER BY total_conversions DESC, total_sessions DESC;

-- Widok: Frazy kluczowe z najlepszym pozycjonowaniem
CREATE VIEW IF NOT EXISTS v_top_keywords AS
SELECT 
    k.keyword,
    a.title_pl as article_title,
    c.name_pl as category_name,
    MIN(k.current_position) as best_position,
    COUNT(DISTINCT k.article_id) as articles_count,
    SUM(k.search_volume) as total_volume
FROM keyword_tracking k
LEFT JOIN articles a ON k.article_id = a.id
LEFT JOIN categories c ON a.category_id = c.id
WHERE k.current_position <= 20  -- tylko top 20 w Google
GROUP BY k.keyword
ORDER BY best_position ASC, total_volume DESC
LIMIT 50;

-- Widok: Konwersje z artykułów
CREATE VIEW IF NOT EXISTS v_article_conversions AS
SELECT 
    a.title_pl,
    c.name_pl as category_name,
    ac.conversion_type,
    COUNT(ac.id) as conversion_count,
    AVG(ac.lead_score) as avg_lead_score,
    SUM(ac.conversion_value) as total_value
FROM article_conversions ac
JOIN articles a ON ac.article_id = a.id
LEFT JOIN categories c ON a.category_id = c.id
WHERE ac.conversion_date >= date('now', '-90 days')
GROUP BY ac.article_id, ac.conversion_type
ORDER BY conversion_count DESC, total_value DESC;