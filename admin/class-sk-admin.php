<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_sk_blogger_action', [ $this, 'handle_actions' ] );
    }

    public function menu() {
        add_menu_page(
            __( 'SK Blogger', 'sk-blogger' ),
            __( 'SK Blogger', 'sk-blogger' ),
            'manage_options',
            'sk-blogger',
            [ $this, 'dashboard_page' ],
            'dashicons-welcome-write-blog',
            30
        );

        add_submenu_page( 'sk-blogger', __( 'Dashboard', 'sk-blogger' ), __( 'Dashboard', 'sk-blogger' ), 'manage_options', 'sk-blogger', [ $this, 'dashboard_page' ] );
        add_submenu_page( 'sk-blogger', __( 'Queue', 'sk-blogger' ), __( 'Queue', 'sk-blogger' ), 'manage_options', 'sk-blogger-queue', [ $this, 'queue_page' ] );
        add_submenu_page( 'sk-blogger', __( 'Topics', 'sk-blogger' ), __( 'Topics', 'sk-blogger' ), 'manage_options', 'sk-blogger-topics', [ $this, 'topics_page' ] );
        add_submenu_page( 'sk-blogger', __( 'Trending Keywords', 'sk-blogger' ), __( 'Trending Keywords', 'sk-blogger' ), 'manage_options', 'sk-blogger-trending', [ $this, 'trending_page' ] );
        add_submenu_page( 'sk-blogger', __( 'Logs', 'sk-blogger' ), __( 'Logs', 'sk-blogger' ), 'manage_options', 'sk-blogger-logs', [ $this, 'logs_page' ] );
        add_submenu_page( 'sk-blogger', __( 'Settings', 'sk-blogger' ), __( 'Settings', 'sk-blogger' ), 'manage_options', 'sk-blogger-settings', [ $this, 'settings_page' ] );
    }

    public function register_settings() {
        $fields = [
            // AI Provider
            'sk_ai_provider', 'sk_openai_key', 'sk_openai_model',
            'sk_gemini_key', 'sk_gemini_model',
            'sk_claude_key', 'sk_claude_model',

            // Post Defaults
            'sk_default_status', 'sk_default_author', 'sk_default_category',
            'sk_post_length', 'sk_tone', 'sk_language',

            // Images
            'sk_image_provider', 'sk_include_images', 'sk_image_count',

            // SEO
            'sk_seo_enabled',
            'sk_keyword_placement_enabled',
            'sk_internal_links_enabled',
            'sk_external_links_enabled',
            'sk_schema_enabled',

            // Auto Topics
            'sk_auto_topics_enabled',
            'sk_auto_topics_niche',
            'sk_auto_topics_keywords',
            'sk_auto_topics_count',
            'sk_auto_topics_threshold',

            // Queue
            'sk_queue_batch', 'sk_max_attempts', 'sk_posts_per_day',

            // Trending Keywords
            'sk_trending_enabled', 'sk_trending_hour',
            'sk_reuse_keywords_enabled',   // ← NEW: fallback reuse toggle
        ];

        foreach ( $fields as $f ) {
            register_setting( 'sk_blogger_settings', $f );
        }
    }

    private function render( $view, $vars = [] ) {
        extract( $vars );
        include SK_BLOGGER_PATH . 'admin/views/' . $view . '.php';
    }

    /* ============================================================
     * PAGE RENDERERS
     * ============================================================ */

    public function dashboard_page() {
        $counts       = SK_DB::queue_counts();
        $recent       = SK_Logger::get_logs( 10 );
        $recent_posts = get_posts( [ 'numberposts' => 5, 'post_status' => [ 'publish', 'draft' ] ] );
        $trending     = SK_DB::trending_counts();
        $this->render( 'dashboard', compact( 'counts', 'recent', 'recent_posts', 'trending' ) );
    }

    public function queue_page() {
        $this->handle_post_actions();
        $queue = SK_DB::get_queue( null, 100 );
        $this->render( 'queue', compact( 'queue' ) );
    }

    public function topics_page() {
        $this->handle_post_actions();
        $topics = SK_DB::get_topics( 100 );
        $this->render( 'topics', compact( 'topics' ) );
    }

    public function trending_page() {
        $this->handle_post_actions();
        $trending = SK_DB::get_trending_keywords( 200 );
        $counts   = SK_DB::trending_counts();
        $this->render( 'trending', compact( 'trending', 'counts' ) );
    }

    public function logs_page() {
        if ( isset( $_POST['sk_clear_logs'] ) && check_admin_referer( 'sk_clear_logs' ) ) {
            SK_Logger::clear();
        }
        $level = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : null;
        $logs  = SK_Logger::get_logs( 200, 0, $level );
        $this->render( 'logs', compact( 'logs', 'level' ) );
    }

    public function settings_page() {
        $this->render( 'settings' );
    }

    /* ============================================================
     * POST ACTIONS HANDLER
     * ============================================================ */

    private function handle_post_actions() {
        if ( ! isset( $_POST['sk_action'] ) ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        check_admin_referer( 'sk_admin_action' );

        $action = sanitize_key( $_POST['sk_action'] );

        switch ( $action ) {

            /* ---------- QUEUE ---------- */

            case 'add_queue':
                $category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
                SK_DB::enqueue(
                    sanitize_text_field( $_POST['topic'] ?? '' ),
                    sanitize_text_field( $_POST['keywords'] ?? '' ),
                    (int) ( $_POST['priority'] ?? 5 ),
                    null,
                    $category_id
                );
                break;

            case 'bulk_queue':
                $lines       = array_filter( array_map( 'trim', explode( "\n", $_POST['bulk_topics'] ?? '' ) ) );
                $category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
                SK_Queue::add_bulk(
                    $lines,
                    sanitize_text_field( $_POST['bulk_keywords'] ?? '' ),
                    5,
                    $category_id
                );
                break;

            case 'delete_queue':
                SK_DB::delete_queue( (int) ( $_POST['id'] ?? 0 ) );
                break;

            case 'process_now':
                SK_Queue::process();
                break;

            case 'reset_failed':
                global $wpdb;
                $wpdb->query(
                    "UPDATE " . SK_DB::table( 'queue' ) . " 
                     SET status = 'pending', attempts = 0, error = NULL, scheduled_at = NOW() 
                     WHERE status = 'failed'"
                );
                break;

            /* ---------- TOPICS ---------- */

            case 'add_topic':
                $category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : null;
                SK_DB::add_topic(
                    sanitize_text_field( $_POST['title'] ?? '' ),
                    sanitize_text_field( $_POST['keywords'] ?? '' ),
                    $category_id
                );
                break;

            case 'delete_topic':
                SK_DB::delete_topic( (int) ( $_POST['id'] ?? 0 ) );
                break;

            case 'generate_topics_now':
                if ( class_exists( 'SK_Cron' ) ) {
                    SK_Cron::auto_generate_topics();
                }
                break;

            /* ---------- TRENDING KEYWORDS ---------- */

            case 'add_trending':
                $cat_id  = (int) ( $_POST['category_id'] ?? 0 );
                $date    = sanitize_text_field( $_POST['trend_date'] ?? current_time( 'Y-m-d' ) );
                $keyword = sanitize_text_field( $_POST['keyword'] ?? '' );
                $kws     = sanitize_text_field( $_POST['keywords'] ?? '' );

                if ( $cat_id > 0 && ! empty( $keyword ) ) {
                    SK_DB::add_trending_keyword( $keyword, $cat_id, $date, $kws );
                }
                break;

            case 'bulk_trending':
                $lines   = array_filter( array_map( 'trim', explode( "\n", $_POST['bulk_keywords'] ?? '' ) ) );
                $cat_id  = (int) ( $_POST['category_id'] ?? 0 );
                $date    = sanitize_text_field( $_POST['trend_date'] ?? current_time( 'Y-m-d' ) );
                $kws     = sanitize_text_field( $_POST['keywords'] ?? '' );

                if ( $cat_id > 0 && ! empty( $lines ) ) {
                    foreach ( $lines as $kw ) {
                        SK_DB::add_trending_keyword( $kw, $cat_id, $date, $kws );
                    }
                }
                break;

            case 'delete_trending':
                SK_DB::delete_trending_keyword( (int) ( $_POST['id'] ?? 0 ) );
                break;

            case 'run_trending_now':
                if ( class_exists( 'SK_Cron' ) ) {
                    SK_Cron::process_daily_trending();
                }
                break;
        }
    }

    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden' ); }
        check_admin_referer( 'sk_blogger_action' );
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=sk-blogger' ) );
        exit;
    }
}