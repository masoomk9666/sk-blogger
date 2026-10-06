<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'Queue', 'sk-blogger' ); ?></h1>

    <div class="sk-two-cols">
        <div class="sk-box">
            <h2><?php esc_html_e( 'Add Single Topic', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="add_queue">
                <p><label><?php esc_html_e( 'Topic', 'sk-blogger' ); ?><br>
                    <input type="text" name="topic" class="regular-text" required></label></p>
                <p><label><?php esc_html_e( 'Keywords (comma separated)', 'sk-blogger' ); ?><br>
                    <input type="text" name="keywords" class="regular-text"></label></p>
                <p><label><?php esc_html_e( 'Priority (1=high, 10=low)', 'sk-blogger' ); ?><br>
                    <input type="number" name="priority" value="5" min="1" max="10"></label></p>
                <p><button class="button button-primary"><?php esc_html_e( 'Add to Queue', 'sk-blogger' ); ?></button></p>
            </form>
        </div>

        <div class="sk-box">
            <h2><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="bulk_queue">
                <p><label><?php esc_html_e( 'One topic per line', 'sk-blogger' ); ?><br>
                    <textarea name="bulk_topics" rows="8" class="large-text"></textarea></label></p>
                <p><label><?php esc_html_e( 'Shared keywords', 'sk-blogger' ); ?><br>
                    <input type="text" name="bulk_keywords" class="regular-text"></label></p>
                <p><button class="button button-primary"><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></button></p>
            </form>
        </div>
    </div>

    <h2><?php esc_html_e( 'Queue Items', 'sk-blogger' ); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th><th>Topic</th><th>Keywords</th><th>Status</th>
                <th>Attempts</th><th>Scheduled</th><th>Post</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $queue as $item ) : ?>
            <tr>
                <td><?php echo (int) $item->id; ?></td>
                <td><?php echo esc_html( $item->topic ); ?></td>
                <td><?php echo esc_html( $item->keywords ); ?></td>
                <td><span class="sk-status sk-<?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( $item->status ); ?></span></td>
                <td><?php echo (int) $item->attempts; ?></td>
                <td><?php echo esc_html( $item->scheduled_at ); ?></td>
                <td>
                    <?php if ( $item->post_id ) : ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $item->post_id ) ); ?>">#<?php echo (int) $item->post_id; ?></a>
                    <?php else : ?>—<?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'sk_admin_action' ); ?>
                        <input type="hidden" name="sk_action" value="delete_queue">
                        <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
                        <button class="button button-small sk-delete">×</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>