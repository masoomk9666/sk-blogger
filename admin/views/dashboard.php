<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'SK Blogger — Dashboard', 'sk-blogger' ); ?></h1>

    <div class="sk-cards">
        <div class="sk-card">
            <h3><?php esc_html_e( 'Pending', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['pending']; ?></p>
        </div>
        <div class="sk-card">
            <h3><?php esc_html_e( 'Processing', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['processing']; ?></p>
        </div>
        <div class="sk-card">
            <h3><?php esc_html_e( 'Completed', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['completed']; ?></p>
        </div>
        <div class="sk-card">
            <h3><?php esc_html_e( 'Failed', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['failed']; ?></p>
        </div>
    </div>

    <div class="sk-two-cols">
        <div>
            <h2><?php esc_html_e( 'Quick Actions', 'sk-blogger' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-queue' ) ); ?>">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="process_now">
                <button class="button button-primary" id="sk-process-now"><?php esc_html_e( 'Process Queue Now', 'sk-blogger' ); ?></button>
            </form>
        </div>
        <div>
            <h2><?php esc_html_e( 'Recent Posts', 'sk-blogger' ); ?></h2>
            <ul class="sk-list">
                <?php foreach ( $recent_posts as $p ) : ?>
                    <li>
                        <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>">
                            <?php echo esc_html( $p->post_title ); ?>
                        </a>
                        <span class="sk-status sk-<?php echo esc_attr( $p->post_status ); ?>"><?php echo esc_html( $p->post_status ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h2><?php esc_html_e( 'Recent Activity', 'sk-blogger' ); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>Level</th><th>Context</th><th>Message</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ( $recent as $l ) : ?>
            <tr>
                <td><span class="sk-badge sk-<?php echo esc_attr( $l->level ); ?>"><?php echo esc_html( $l->level ); ?></span></td>
                <td><?php echo esc_html( $l->context ); ?></td>
                <td><?php echo esc_html( $l->message ); ?></td>
                <td><?php echo esc_html( $l->created_at ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>