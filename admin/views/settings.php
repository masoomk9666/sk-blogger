<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'SK Blogger Settings', 'sk-blogger' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'sk_blogger_settings' ); ?>

        <h2><?php esc_html_e( 'AI Provider', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Provider', 'sk-blogger' ); ?></th>
                <td>
                    <select name="sk_ai_provider">
                        <?php $p = get_option( 'sk_ai_provider' ); ?>
                        <option value="openai" <?php selected( $p, 'openai' ); ?>>OpenAI</option>
                        <option value="gemini" <?php selected( $p, 'gemini' ); ?>>Google Gemini</option>
                        <option value="claude" <?php selected( $p, 'claude' ); ?>>Anthropic Claude</option>
                    </select>
                </td>
            </tr>
            <tr><th>OpenAI API Key</th><td><input type="password" name="sk_openai_key" value="<?php echo esc_attr( get_option( 'sk_openai_key' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>OpenAI Model</th><td><input type="text" name="sk_openai_model" value="<?php echo esc_attr( get_option( 'sk_openai_model' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>Gemini API Key</th><td><input type="password" name="sk_gemini_key" value="<?php echo esc_attr( get_option( 'sk_gemini_key' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>Gemini Model</th><td><input type="text" name="sk_gemini_model" value="<?php echo esc_attr( get_option( 'sk_gemini_model' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>Claude API Key</th><td><input type="password" name="sk_claude_key" value="<?php echo esc_attr( get_option( 'sk_claude_key' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>Claude Model</th><td><input type="text" name="sk_claude_model" value="<?php echo esc_attr( get_option( 'sk_claude_model' ) ); ?>" class="regular-text"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Post Defaults', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>Post Status</th>
                <td>
                    <select name="sk_default_status">
                        <?php $s = get_option( 'sk_default_status' ); ?>
                        <option value="draft" <?php selected( $s, 'draft' ); ?>>Draft</option>
                        <option value="publish" <?php selected( $s, 'publish' ); ?>>Publish</option>
                        <option value="pending" <?php selected( $s, 'pending' ); ?>>Pending</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Default Author</th>
                <td>
                    <?php wp_dropdown_users( [ 'name' => 'sk_default_author', 'selected' => get_option( 'sk_default_author' ) ] ); ?>
                </td>
            </tr>
            <tr>
                <th>Default Category</th>
                <td><?php wp_dropdown_categories( [ 'name' => 'sk_default_category', 'selected' => get_option( 'sk_default_category' ), 'hide_empty' => 0 ] ); ?></td>
            </tr>
            <tr>
                <th>Post Length</th>
                <td>
                    <select name="sk_post_length">
                        <?php $l = get_option( 'sk_post_length' ); ?>
                        <option value="short" <?php selected( $l, 'short' ); ?>>Short (~600 words)</option>
                        <option value="medium" <?php selected( $l, 'medium' ); ?>>Medium (~1200 words)</option>
                        <option value="long" <?php selected( $l, 'long' ); ?>>Long (~2000 words)</option>
                    </select>
                </td>
            </tr>
            <tr><th>Tone</th><td><input type="text" name="sk_tone" value="<?php echo esc_attr( get_option( 'sk_tone' ) ); ?>" class="regular-text"></td></tr>
            <tr><th>Language</th><td><input type="text" name="sk_language" value="<?php echo esc_attr( get_option( 'sk_language' ) ); ?>" class="small-text"> <em>e.g. en, es, fr, bn</em></td></tr>
        </table>

        <h2><?php esc_html_e( 'Images', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>Image Provider</th>
                <td>
                    <select name="sk_image_provider">
    <?php $ip = get_option( 'sk_image_provider', 'gemini' ); ?>
    <option value="gemini" <?php selected( $ip, 'gemini' ); ?>>Google Gemini (Nano Banana)</option>
    <option value="openai" <?php selected( $ip, 'openai' ); ?>>OpenAI DALL·E 3</option>
    <option value="pollinations" <?php selected( $ip, 'pollinations' ); ?>>Pollinations (free fallback)</option>
</select>
<p class="description">
    <?php esc_html_e( 'Gemini uses your existing Gemini API key. No extra setup needed.', 'sk-blogger' ); ?>
</p>
                </td>
            </tr>
            <tr><th>Include Images</th><td><label><input type="checkbox" name="sk_include_images" value="1" <?php checked( get_option( 'sk_include_images' ), 1 ); ?>> Yes</label></td></tr>
            <tr><th>Image Count</th><td><input type="number" name="sk_image_count" value="<?php echo (int) get_option( 'sk_image_count', 1 ); ?>" min="0" max="5"></td></tr>
        </table>

        <h2><?php esc_html_e( 'SEO & Queue', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr><th>SEO Meta</th><td><label><input type="checkbox" name="sk_seo_enabled" value="1" <?php checked( get_option( 'sk_seo_enabled' ), 1 ); ?>> Enable Yoast / RankMath / AIOSEO meta</label></td></tr>
            <tr><th>Queue Batch Size</th><td><input type="number" name="sk_queue_batch" value="<?php echo (int) get_option( 'sk_queue_batch' ); ?>" min="1" max="20"></td></tr>
            <tr><th>Max Attempts</th><td><input type="number" name="sk_max_attempts" value="<?php echo (int) get_option( 'sk_max_attempts' ); ?>" min="1" max="10"></td></tr>
            <tr><th>Posts Per Day (auto)</th><td><input type="number" name="sk_posts_per_day" value="<?php echo (int) get_option( 'sk_posts_per_day' ); ?>" min="1" max="50"></td></tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>