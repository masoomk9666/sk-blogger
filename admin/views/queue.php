<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'Queue', 'sk-blogger' ); ?></h1>

    <!-- ============================================================
         ADD FORMS
         ============================================================ -->
    <div class="sk-two-cols">
        <!-- ===== Single Topic Form ===== -->
        <div class="sk-box">
            <h2><?php esc_html_e( 'Add Single Topic', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="add_queue">

                <p>
                    <label><?php esc_html_e( 'Topic', 'sk-blogger' ); ?><br>
                        <input type="text" name="topic" class="regular-text" required 
                               placeholder="e.g. How AI is Transforming Mechanical Engineering">
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Keywords (comma separated)', 'sk-blogger' ); ?><br>
                        <input type="text" name="keywords" class="regular-text" 
                               placeholder="e.g. AI, CAD, automation, machine learning">
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Category', 'sk-blogger' ); ?><br>
                        <?php
                        wp_dropdown_categories( [
                            'name'             => 'category_id',
                            'id'               => 'sk_category_single',
                            'selected'         => get_option( 'sk_default_category', 1 ),
                            'hide_empty'       => 0,
                            'orderby'          => 'name',
                            'order'            => 'ASC',
                            'show_count'       => 1,
                            'hierarchical'     => 1,
                            'class'            => 'regular-text',
                            'show_option_none' => __( '— Select Category —', 'sk-blogger' ),
                            'option_none_value'=> '0',
                        ] );
                        ?>
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Priority (1=high, 10=low)', 'sk-blogger' ); ?><br>
                        <input type="number" name="priority" value="5" min="1" max="10">
                    </label>
                </p>

                <p>
                    <button class="button button-primary"><?php esc_html_e( 'Add to Queue', 'sk-blogger' ); ?></button>
                </p>
            </form>
        </div>

        <!-- ===== Bulk Add Form ===== -->
        <div class="sk-box">
            <h2><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="bulk_queue">

                <p>
                    <label><?php esc_html_e( 'One topic per line', 'sk-blogger' ); ?><br>
                        <textarea name="bulk_topics" rows="8" class="large-text" 
                                  placeholder="Topic 1&#10;Topic 2&#10;Topic 3"></textarea>
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Shared keywords', 'sk-blogger' ); ?><br>
                        <input type="text" name="bulk_keywords" class="regular-text" 
                               placeholder="e.g. AI, CAD, automation">
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Category (applies to all)', 'sk-blogger' ); ?><br>
                        <?php
                        wp_dropdown_categories( [
                            'name'             => 'category_id',
                            'id'               => 'sk_category_bulk',
                            'selected'         => get_option( 'sk_default_category', 1 ),
                            'hide_empty'       => 0,
                            'orderby'          => 'name',
                            'order'            => 'ASC',
                            'show_count'       => 1,
                            'hierarchical'     => 1,
                            'class'            => 'regular-text',
                            'show_option_none' => __( '— Select Category —', 'sk-blogger' ),
                            'option_none_value'=> '0',
                        ] );
                        ?>
                    </label>
                </p>

                <p>
                    <button class="button button-primary"><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- ============================================================
         QUEUE TABLE
         ============================================================ -->
    <h2><?php esc_html_e( 'Queue Items', 'sk-blogger' ); ?></h2>

    <?php if ( empty( $queue ) ) : ?>
        <p><em><?php esc_html_e( 'No items in queue. Add topics above to get started.', 'sk-blogger' ); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th><?php esc_html_e( 'Topic', 'sk-blogger' ); ?></th>
                    <th style="width:200px;"><?php esc_html_e( 'Keywords', 'sk-blogger' ); ?></th>
                    <th style="width:130px;"><?php esc_html_e( 'Category', 'sk-blogger' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Status', 'sk-blogger' ); ?></th>
                    <th style="width:70px;"><?php esc_html_e( 'Attempts', 'sk-blogger' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Scheduled', 'sk-blogger' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Post', 'sk-blogger' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Actions', 'sk-blogger' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $queue as $item ) : ?>
                <tr>
                    <td><?php echo (int) $item->id; ?></td>
                    <td><?php echo esc_html( wp_trim_words( $item->topic, 12 ) ); ?></td>
                    <td><small><?php echo esc_html( wp_trim_words( $item->keywords, 8 ) ); ?></small></td>
                    <td>
                        <?php
                        // Show category name
                        $cat_id = isset( $item->category_id ) ? (int) $item->category_id : 0;
                        if ( $cat_id > 0 ) {
                            $cat = get_category( $cat_id );
                            if ( $cat && ! is_wp_error( $cat ) ) {
                                echo '<a href="' . esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=category&tag_ID=' . $cat_id ) ) . '">'
                                   . esc_html( $cat->name ) . '</a>';
                            } else {
                                echo '<em style="color:#999;">' . esc_html__( 'Deleted', 'sk-blogger' ) . '</em>';
                            }
                        } else {
                            echo '<em style="color:#999;">' . esc_html__( 'Default', 'sk-blogger' ) . '</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="sk-status sk-<?php echo esc_attr( $item->status ); ?>">
                            <?php echo esc_html( $item->status ); ?>
                        </span>
                    </td>
                    <td><?php echo (int) $item->attempts; ?></td>
                    <td><small><?php echo esc_html( $item->scheduled_at ); ?></small></td>
                    <td>
                        <?php if ( $item->post_id ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $item->post_id ) ); ?>" 
                               title="Edit post">#<?php echo (int) $item->post_id; ?></a>
                        <?php else : ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('<?php echo esc_js( __( 'Delete this item?', 'sk-blogger' ) ); ?>');">
                            <?php wp_nonce_field( 'sk_admin_action' ); ?>
                            <input type="hidden" name="sk_action" value="delete_queue">
                            <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
                            <button class="button button-small sk-delete" title="Delete">×</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ============================================================
         BULK ACTIONS
         ============================================================ -->
    <p style="margin-top:15px;">
        <form method="post" style="display:inline">
            <?php wp_nonce_field( 'sk_admin_action' ); ?>
            <input type="hidden" name="sk_action" value="process_now">
            <button class="button button-primary">
                <?php esc_html_e( '▶ Process Queue Now', 'sk-blogger' ); ?>
            </button>
        </form>

        <?php
        // Check if any failed items
        global $wpdb;
        $failed_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . SK_DB::table( 'queue' ) . " WHERE status = 'failed'"
        );
        if ( $failed_count > 0 ) :
        ?>
            <form method="post" style="display:inline" 
                  onsubmit="return confirm('Reset <?php echo (int) $failed_count; ?> failed items to pending?');">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="reset_failed">
                <button class="button">
                    <?php printf( esc_html__( '↻ Reset Failed (%d)', 'sk-blogger' ), $failed_count ); ?>
                </button>
            </form>
        <?php endif; ?>
    </p>
</div>