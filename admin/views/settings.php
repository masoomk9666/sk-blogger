<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'SK Blogger Settings', 'sk-blogger' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'sk_blogger_settings' ); ?>

        <!-- ============================================================
             AI PROVIDER
             ============================================================ -->
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
            <tr>
                <th>OpenAI API Key</th>
                <td><input type="password" name="sk_openai_key" value="<?php echo esc_attr( get_option( 'sk_openai_key' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>OpenAI Model</th>
                <td><input type="text" name="sk_openai_model" value="<?php echo esc_attr( get_option( 'sk_openai_model' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Gemini API Key</th>
                <td><input type="password" name="sk_gemini_key" value="<?php echo esc_attr( get_option( 'sk_gemini_key' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Gemini Model</th>
                <td>
                    <input type="text" name="sk_gemini_model" value="<?php echo esc_attr( get_option( 'sk_gemini_model' ) ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Recommended: gemini-3.5-flash (free tier, fast)', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th>Claude API Key</th>
                <td><input type="password" name="sk_claude_key" value="<?php echo esc_attr( get_option( 'sk_claude_key' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Claude Model</th>
                <td><input type="text" name="sk_claude_model" value="<?php echo esc_attr( get_option( 'sk_claude_model' ) ); ?>" class="regular-text"></td>
            </tr>
        </table>

        <!-- ============================================================
             POST DEFAULTS
             ============================================================ -->
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
            <tr>
                <th>Tone</th>
                <td><input type="text" name="sk_tone" value="<?php echo esc_attr( get_option( 'sk_tone' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Language</th>
                <td>
                    <input type="text" name="sk_language" value="<?php echo esc_attr( get_option( 'sk_language' ) ); ?>" class="small-text">
                    <em><?php esc_html_e( 'e.g. en, es, fr, bn', 'sk-blogger' ); ?></em>
                </td>
            </tr>
        </table>

        <!-- ============================================================
             IMAGES
             ============================================================ -->
        <h2><?php esc_html_e( 'Images', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>Image Provider</th>
                <td>
                    <select name="sk_image_provider">
                        <?php $ip = get_option( 'sk_image_provider', 'pollinations' ); ?>
                        <option value="pollinations" <?php selected( $ip, 'pollinations' ); ?>>Pollinations (Free, Unlimited)</option>
                        <option value="gemini" <?php selected( $ip, 'gemini' ); ?>>Google Gemini (Paid Tier Only)</option>
                        <option value="openai" <?php selected( $ip, 'openai' ); ?>>OpenAI DALL·E 3 (Paid)</option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Pollinations is 100% free — recommended for free-tier users. Gemini image models require paid tier.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>Include Images</th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_include_images" value="1" <?php checked( get_option( 'sk_include_images' ), 1 ); ?>>
                        <?php esc_html_e( 'Add featured image to each post', 'sk-blogger' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th>Image Count</th>
                <td><input type="number" name="sk_image_count" value="<?php echo (int) get_option( 'sk_image_count', 1 ); ?>" min="0" max="5"></td>
            </tr>
        </table>

        <!-- ============================================================
             SEO ENHANCEMENTS (NEW)
             ============================================================ -->
        <h2><?php esc_html_e( 'SEO Enhancements', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'SEO Meta', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_seo_enabled" value="1" <?php checked( get_option( 'sk_seo_enabled', 1 ), 1 ); ?>>
                        <?php esc_html_e( 'Auto-generate meta title (50-60 chars) & description (145-160 chars)', 'sk-blogger' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Compatible with Yoast SEO, RankMath, and All in One SEO.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Keyword Placement', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_keyword_placement_enabled" value="1" <?php checked( get_option( 'sk_keyword_placement_enabled', 1 ), 1 ); ?>>
                        <?php esc_html_e( 'Ensure keyword appears in slug, H1, first paragraph, H2, and image alt', 'sk-blogger' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Internal Linking', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_internal_links_enabled" value="1" <?php checked( get_option( 'sk_internal_links_enabled', 1 ), 1 ); ?>>
                        <?php esc_html_e( 'Auto-add internal links to related posts/categories', 'sk-blogger' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Links will be inserted naturally within post content.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'External Linking', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_external_links_enabled" value="1" <?php checked( get_option( 'sk_external_links_enabled', 1 ), 1 ); ?>>
                        <?php esc_html_e( 'Add authority external links (Google, IEEE, Moz, etc.)', 'sk-blogger' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'External links use rel="noopener nofollow" for safety.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Schema Markup', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_schema_enabled" value="1" <?php checked( get_option( 'sk_schema_enabled', 1 ), 1 ); ?>>
                        <?php esc_html_e( 'Add BlogPosting JSON-LD schema for rich results', 'sk-blogger' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Improves SEO by helping Google understand your content structure.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- ============================================================
             AUTO TOPIC GENERATION
             ============================================================ -->
        <h2><?php esc_html_e( 'Auto Topic Generation', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Enable Auto Topics', 'sk-blogger' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sk_auto_topics_enabled" value="1" <?php checked( get_option( 'sk_auto_topics_enabled', 0 ), 1 ); ?>>
                        <?php esc_html_e( 'Automatically generate topic ideas when queue is empty', 'sk-blogger' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'AI will generate new topics based on your niche when the queue runs low.', 'sk-blogger' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Niche / Industry', 'sk-blogger' ); ?></th>
                <td>
                    <input type="text" name="sk_auto_topics_niche" value="<?php echo esc_attr( get_option( 'sk_auto_topics_niche', '' ) ); ?>" class="regular-text" placeholder="e.g. Mechanical Engineering, Digital Marketing, Health & Fitness">
                    <p class="description"><?php esc_html_e( 'AI will generate topics in this niche.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Target Keywords', 'sk-blogger' ); ?></th>
                <td>
                    <input type="text" name="sk_auto_topics_keywords" value="<?php echo esc_attr( get_option( 'sk_auto_topics_keywords', '' ) ); ?>" class="regular-text" placeholder="e.g. AI, machine learning, automation">
                    <p class="description"><?php esc_html_e( 'Comma-separated keywords AI should focus on.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Topics Per Batch', 'sk-blogger' ); ?></th>
                <td>
                    <input type="number" name="sk_auto_topics_count" value="<?php echo (int) get_option( 'sk_auto_topics_count', 10 ); ?>" min="1" max="50" class="small-text">
                    <p class="description"><?php esc_html_e( 'How many topics to generate each time.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Minimum Queue Threshold', 'sk-blogger' ); ?></th>
                <td>
                    <input type="number" name="sk_auto_topics_threshold" value="<?php echo (int) get_option( 'sk_auto_topics_threshold', 3 ); ?>" min="0" max="20" class="small-text">
                    <p class="description"><?php esc_html_e( 'When pending queue falls below this number, generate more topics.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
        </table>

        <!-- ============================================================
             QUEUE SETTINGS
             ============================================================ -->
        <h2><?php esc_html_e( 'Queue Settings', 'sk-blogger' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>Queue Batch Size</th>
                <td>
                    <input type="number" name="sk_queue_batch" value="<?php echo (int) get_option( 'sk_queue_batch', 1 ); ?>" min="1" max="20">
                    <p class="description"><?php esc_html_e( 'Items processed per cron run. Use 1 for free tier.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th>Max Attempts</th>
                <td>
                    <input type="number" name="sk_max_attempts" value="<?php echo (int) get_option( 'sk_max_attempts', 3 ); ?>" min="1" max="10">
                    <p class="description"><?php esc_html_e( 'How many times to retry on failure.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
            <tr>
                <th>Posts Per Day</th>
                <td>
                    <input type="number" name="sk_posts_per_day" value="<?php echo (int) get_option( 'sk_posts_per_day', 5 ); ?>" min="1" max="50">
                    <p class="description"><?php esc_html_e( 'Target daily posts for auto-generation.', 'sk-blogger' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>