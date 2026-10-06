<?php
/**
 * Plugin Name:       SK Blogger
 * Plugin URI:        https://example.com/sk-blogger
 * Description:       AI-powered WordPress auto blogger with OpenAI/Gemini/Claude support, queue processing, image generation, and SEO.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SK
 * License:           GPL-2.0-or-later
 * Text Domain:       sk-blogger
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SK_BLOGGER_VERSION', '1.0.0' );
define( 'SK_BLOGGER_FILE', __FILE__ );
define( 'SK_BLOGGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SK_BLOGGER_URL', plugin_dir_url( __FILE__ ) );
define( 'SK_BLOGGER_BASENAME', plugin_basename( __FILE__ ) );

require_once SK_BLOGGER_PATH . 'includes/class-sk-db.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-logger.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-ai.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-image.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-seo.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-generator.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-queue.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-cron.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-rest.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-activator.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-deactivator.php';
require_once SK_BLOGGER_PATH . 'includes/class-sk-blogger.php';

if ( is_admin() ) {
    require_once SK_BLOGGER_PATH . 'admin/class-sk-admin.php';
}

register_activation_hook( __FILE__, [ 'SK_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SK_Deactivator', 'deactivate' ] );

function sk_blogger() {
    return SK_Blogger::instance();
}
add_action( 'plugins_loaded', 'sk_blogger' );