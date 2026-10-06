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

            // SEO Enhancements
            'sk_seo_enabled',
            'sk_keyword_placement_enabled',
            'sk_internal_links_enabled',
            'sk_external_links_enabled',
            'sk_schema_enabled',

            // Auto Topic Generation
            'sk_auto_topics_enabled',
            'sk_auto_topics_niche',
            'sk_auto_topics_keywords',
            'sk_auto_topics_count',
            'sk_auto_topics_threshold',

            // Queue Settings
            'sk_queue_batch', 'sk_max_attempts', 'sk_posts_per_day',
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
        $this->render( 'dashboard', compact( 'counts', 'recent', 'recent_posts' ) );
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

            case 'add_queue':
                SK_DB::enqueue(
                    sanitize_text_field( $_POST['topic'] ?? '' ),
                    sanitize_text_field( $_POST['keywords'] ?? '' ),
                    (int) ( $_POST['priority'] ?? 5 )
                );
                break;

            case 'bulk_queue':
                $lines = array_filter( array_map( 'trim', explode( "\n", $_POST['bulk_topics'] ?? '' ) ) );
                SK_Queue::add_bulk( $lines, sanitize_text_field( $_POST['bulk_keywords'] ?? '' ) );
                break;

            case 'delete_queue':
                SK_DB::delete_queue( (int) ( $_POST['id'] ?? 0 ) );
                break;

            case 'add_topic':
                SK_DB::add_topic(
                    sanitize_text_field( $_POST['title'] ?? '' ),
                    sanitize_text_field( $_POST['keywords'] ?? '' )
                );
                break;

            case 'delete_topic':
                SK_DB::delete_topic( (int) ( $_POST['id'] ?? 0 ) );
                break;

            case 'process_now':
                SK_Queue::process();
                break;

            case 'generate_topics_now':
                // Manually trigger AI topic generation
                if ( class_exists( 'SK_Cron' ) ) {
                    SK_Cron::auto_generate_topics();
                }
                break;

            case 'reset_failed':
                // Reset all failed items to pending
                global $wpdb;
                $wpdb->query(
                    "UPDATE " . SK_DB::table( 'queue' ) . " 
                     SET status = 'pending', attempts = 0, error = NULL, scheduled_at = NOW() 
                     WHERE status = 'failed'"
                );
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