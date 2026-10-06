<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'Logs', 'sk-blogger' ); ?></h1>

    <p>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sk-blogger-logs' ) ); ?>">All</a>
        <a class="button" href="<?php echo esc_url( add_query_arg( 'level', 'info', admin_url( 'admin.php?page=sk-blogger-logs' ) ) ); ?>">Info</a>
        <a class="button" href="<?php echo esc_url( add_query_arg( 'level', 'warning', admin_url( 'admin.php?page=sk-blogger-logs' ) ) ); ?>">Warning</a>
        <a class="button" href="<?php echo esc_url( add_query_arg( 'level', 'error', admin_url( 'admin.php?page=sk-blogger-logs' ) ) ); ?>">Error</a>
    </p>

    <form method="post">
        <?php wp_nonce_field( 'sk_clear_logs' ); ?>
        <input type="hidden" name="sk_clear_logs" value="1">
        <button class="button" onclick="return confirm('Clear all logs?')"><?php esc_html_e( 'Clear Logs', 'sk-blogger' ); ?></button>
    </form>

    <table class="widefat striped">
        <thead><tr><th>ID</th><th>Level</th><th>Context</th><th>Message</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ( $logs as $l ) : ?>
            <tr>
                <td><?php echo (int) $l->id; ?></td>
                <td><span class="sk-badge sk-<?php echo esc_attr( $l->level ); ?>"><?php echo esc_html( $l->level ); ?></span></td>
                <td><?php echo esc_html( $l->context ); ?></td>
                <td><?php echo esc_html( $l->message ); ?></td>
                <td><?php echo esc_html( $l->created_at ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>