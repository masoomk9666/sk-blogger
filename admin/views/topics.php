<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'Topic Ideas', 'sk-blogger' ); ?></h1>

    <div class="sk-box">
        <h2><?php esc_html_e( 'Add Topic Idea', 'sk-blogger' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'sk_admin_action' ); ?>
            <input type="hidden" name="sk_action" value="add_topic">
            <p><label><?php esc_html_e( 'Title', 'sk-blogger' ); ?><br>
                <input type="text" name="title" class="regular-text" required></label></p>
            <p><label><?php esc_html_e( 'Keywords', 'sk-blogger' ); ?><br>
                <input type="text" name="keywords" class="regular-text"></label></p>
            <p><button class="button button-primary"><?php esc_html_e( 'Save Idea', 'sk-blogger' ); ?></button></p>
        </form>
    </div>

    <h2><?php esc_html_e( 'Saved Ideas', 'sk-blogger' ); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th>Title</th><th>Keywords</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $topics as $t ) : ?>
            <tr>
                <td><?php echo (int) $t->id; ?></td>
                <td><?php echo esc_html( $t->title ); ?></td>
                <td><?php echo esc_html( $t->keywords ); ?></td>
                <td><span class="sk-status sk-<?php echo esc_attr( $t->status ); ?>"><?php echo esc_html( $t->status ); ?></span></td>
                <td><?php echo esc_html( $t->created_at ); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'sk_admin_action' ); ?>
                        <input type="hidden" name="sk_action" value="delete_topic">
                        <input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
                        <button class="button button-small sk-delete">×</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>