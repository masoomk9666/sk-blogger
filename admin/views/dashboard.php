<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'SK Blogger — Dashboard', 'sk-blogger' ); ?></h1>

    <!-- ============================================================
         STATS CARDS
         ============================================================ -->
    <div class="sk-cards">
        <div class="sk-card">
            <h3><?php esc_html_e( 'Pending', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['pending']; ?></p>
        </div>
        <div class="sk-card">
            <h3><?php esc_html_e( 'Processing', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['processing']; ?></p>
        </div>
        <div class="sk-card sk-card-success">
            <h3><?php esc_html_e( 'Completed', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['completed']; ?></p>
        </div>
        <div class="sk-card <?php echo $counts['failed'] > 0 ? 'sk-card-error' : ''; ?>">
            <h3><?php esc_html_e( 'Failed', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['failed']; ?></p>
        </div>
    </div>

    <!-- ============================================================
         QUICK ACTIONS + RECENT POSTS
         ============================================================ -->
    <div class="sk-two-cols">
        <div class="sk-box">
            <h2><?php esc_html_e( 'Quick Actions', 'sk-blogger' ); ?></h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-queue' ) ); ?>" style="display:inline-block; margin-right:8px;">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="process_now">
                <button class="button button-primary" id="sk-process-now">
                    <?php esc_html_e( '▶ Process Queue Now', 'sk-blogger' ); ?>
                </button>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-queue' ) ); ?>" style="display:inline-block; margin-right:8px;">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="generate_topics_now">
                <button class="button" onclick="return confirm('Generate new topic ideas with AI?')">
                    <?php esc_html_e( '💡 Generate Topics', 'sk-blogger' ); ?>
                </button>
            </form>

            <?php if ( $counts['failed'] > 0 ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-queue' ) ); ?>" style="display:inline-block;">
                    <?php wp_nonce_field( 'sk_admin_action' ); ?>
                    <input type="hidden" name="sk_action" value="reset_failed">
                    <button class="button" onclick="return confirm('Reset ALL failed items to pending?')">
                        <?php esc_html_e( '↻ Reset Failed', 'sk-blogger' ); ?>
                    </button>
                </form>
            <?php endif; ?>

            <hr style="margin:15px 0;">

            <h3><?php esc_html_e( 'System Status', 'sk-blogger' ); ?></h3>
            <ul class="sk-status-list">
                <?php
                $ai_provider    = get_option( 'sk_ai_provider', 'openai' );
                $image_provider = get_option( 'sk_image_provider', 'pollinations' );
                $gemini_model   = get_option( 'sk_gemini_model', '—' );
                $cron_scheduled = wp_next_scheduled( 'sk_blogger_process_queue' );
                ?>
                <li>
                    <strong><?php esc_html_e( 'AI Provider:', 'sk-blogger' ); ?></strong>
                    <?php echo esc_html( ucfirst( $ai_provider ) ); ?>
                    <?php if ( $ai_provider === 'gemini' ) : ?>
                        <em>(<?php echo esc_html( $gemini_model ); ?>)</em>
                    <?php endif; ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Image Provider:', 'sk-blogger' ); ?></strong>
                    <?php echo esc_html( ucfirst( $image_provider ) ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Cron Status:', 'sk-blogger' ); ?></strong>
                    <?php if ( $cron_scheduled ) : ?>
                        <span class="sk-status sk-completed"><?php esc_html_e( 'Scheduled', 'sk-blogger' ); ?></span>
                        <em><?php echo esc_html( human_time_diff( time(), $cron_scheduled ) ); ?> <?php esc_html_e( 'from now', 'sk-blogger' ); ?></em>
                    <?php else : ?>
                        <span class="sk-status sk-failed"><?php esc_html_e( 'Not Scheduled', 'sk-blogger' ); ?></span>
                    <?php endif; ?>
                </li>
            </ul>

            <h3 style="margin-top:15px;"><?php esc_html_e( 'SEO Features', 'sk-blogger' ); ?></h3>
            <ul class="sk-status-list">
                <li><?php echo get_option( 'sk_seo_enabled', 1 ) ? '✅' : '❌'; ?> <?php esc_html_e( 'SEO Meta', 'sk-blogger' ); ?></li>
                <li><?php echo get_option( 'sk_keyword_placement_enabled', 1 ) ? '✅' : '❌'; ?> <?php esc_html_e( 'Keyword Placement', 'sk-blogger' ); ?></li>
                <li><?php echo get_option( 'sk_internal_links_enabled', 1 ) ? '✅' : '❌'; ?> <?php esc_html_e( 'Internal Links', 'sk-blogger' ); ?></li>
                <li><?php echo get_option( 'sk_external_links_enabled', 1 ) ? '✅' : '❌'; ?> <?php esc_html_e( 'External Links', 'sk-blogger' ); ?></li>
                <li><?php echo get_option( 'sk_schema_enabled', 1 ) ? '✅' : '❌'; ?> <?php esc_html_e( 'Schema Markup', 'sk-blogger' ); ?></li>
            </ul>

            <?php if ( get_option( 'sk_auto_topics_enabled', 0 ) ) : ?>
                <h3 style="margin-top:15px;"><?php esc_html_e( 'Auto Topics', 'sk-blogger' ); ?></h3>
                <ul class="sk-status-list">
                    <li><strong><?php esc_html_e( 'Niche:', 'sk-blogger' ); ?></strong> <?php echo esc_html( get_option( 'sk_auto_topics_niche', '—' ) ?: '—' ); ?></li>
                    <li><strong><?php esc_html_e( 'Batch:', 'sk-blogger' ); ?></strong> <?php echo (int) get_option( 'sk_auto_topics_count', 10 ); ?> <?php esc_html_e( 'topics', 'sk-blogger' ); ?></li>
                    <li><strong><?php esc_html_e( 'Threshold:', 'sk-blogger' ); ?></strong> <?php echo (int) get_option( 'sk_auto_topics_threshold', 3 ); ?></li>
                </ul>
            <?php endif; ?>
        </div>

        <div class="sk-box">
            <h2><?php esc_html_e( 'Recent Posts', 'sk-blogger' ); ?></h2>
            <?php if ( empty( $recent_posts ) ) : ?>
                <p><em><?php esc_html_e( 'No posts yet. Add topics to the queue to get started.', 'sk-blogger' ); ?></em></p>
            <?php else : ?>
                <ul class="sk-list">
                    <?php foreach ( $recent_posts as $p ) :
                        $has_thumb = has_post_thumbnail( $p->ID );
                        $meta_desc = get_post_meta( $p->ID, '_sk_meta_description', true );
                    ?>
                        <li style="margin-bottom:12px;">
                            <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" style="font-weight:600;">
                                <?php echo esc_html( wp_trim_words( $p->post_title, 10 ) ); ?>
                            </a>
                            <span class="sk-status sk-<?php echo esc_attr( $p->post_status ); ?>">
                                <?php echo esc_html( $p->post_status ); ?>
                            </span>
                            <br>
                            <small style="color:#666;">
                                <?php echo $has_thumb ? '🖼️' : '⬜'; ?> Image
                                &nbsp;•&nbsp;
                                <?php echo $meta_desc ? '📝' : '⬜'; ?> Meta
                                &nbsp;•&nbsp;
                                <?php echo esc_html( get_the_date( 'M j, Y', $p->ID ) ); ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         RECENT ACTIVITY
         ============================================================ -->
    <h2><?php esc_html_e( 'Recent Activity', 'sk-blogger' ); ?></h2>

    <?php if ( empty( $recent ) ) : ?>
        <p><em><?php esc_html_e( 'No activity yet.', 'sk-blogger' ); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:80px;"><?php esc_html_e( 'Level', 'sk-blogger' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Context', 'sk-blogger' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'sk-blogger' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Time', 'sk-blogger' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $recent as $l ) :
                $icon_map = [
                    'info'    => 'ℹ️',
                    'warning' => '⚠️',
                    'error'   => '❌',
                    'success' => '✅',
                    'debug'   => '🔍',
                ];
                $icon = $icon_map[ $l->level ] ?? '';
            ?>
                <tr>
                    <td>
                        <span class="sk-badge sk-<?php echo esc_attr( $l->level ); ?>">
                            <?php echo esc_html( $icon . ' ' . $l->level ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $l->context ); ?></td>
                    <td><?php echo esc_html( wp_trim_words( $l->message, 20 ) ); ?></td>
                    <td>
                        <abbr title="<?php echo esc_attr( $l->created_at ); ?>">
                            <?php echo esc_html( human_time_diff( strtotime( $l->created_at ) ) ); ?> <?php esc_html_e( 'ago', 'sk-blogger' ); ?>
                        </abbr>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top:15px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-logs' ) ); ?>" class="button">
            <?php esc_html_e( 'View All Logs →', 'sk-blogger' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-queue' ) ); ?>" class="button">
            <?php esc_html_e( 'Manage Queue →', 'sk-blogger' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-settings' ) ); ?>" class="button">
            <?php esc_html_e( 'Settings →', 'sk-blogger' ); ?>
        </a>
    </p>
</div>