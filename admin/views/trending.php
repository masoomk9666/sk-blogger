<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sk-wrap">
    <h1><?php esc_html_e( 'Trending Keywords', 'sk-blogger' ); ?></h1>

    <p style="background:#fff8e5;padding:12px;border-left:4px solid #f0b849;margin:15px 0;">
        <strong><?php esc_html_e( 'How it works:', 'sk-blogger' ); ?></strong>
        <?php esc_html_e( 'Save trending keywords for each category with a date. Each day at 9:00 AM, the plugin automatically picks ONE keyword per category and adds it to the queue. All posts are generated automatically.', 'sk-blogger' ); ?>
    </p>

    <!-- Stats -->
    <div class="sk-cards">
        <div class="sk-card">
            <h3><?php esc_html_e( 'Today Pending', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['pending']; ?></p>
        </div>
        <div class="sk-card sk-card-success">
            <h3><?php esc_html_e( 'Today Used', 'sk-blogger' ); ?></h3>
            <p class="sk-stat"><?php echo (int) $counts['used']; ?></p>
        </div>
    </div>

    <!-- Add Forms -->
    <div class="sk-two-cols">
        <!-- Single Add -->
        <div class="sk-box">
            <h2><?php esc_html_e( 'Add Single Keyword', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="add_trending">

                <p>
                    <label><?php esc_html_e( 'Keyword / Topic', 'sk-blogger' ); ?><br>
                        <input type="text" name="keyword" class="regular-text" required
                               placeholder="e.g. ChatGPT new features 2026">
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Category', 'sk-blogger' ); ?><br>
                        <?php wp_dropdown_categories( [
                            'name'              => 'category_id',
                            'hide_empty'        => 0,
                            'orderby'           => 'name',
                            'order'             => 'ASC',
                            'show_count'        => 1,
                            'hierarchical'      => 1,
                            'class'             => 'regular-text',
                            'show_option_none'  => __( '— Select Category —', 'sk-blogger' ),
                            'option_none_value' => '0',
                        ] ); ?>
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Trend Date', 'sk-blogger' ); ?><br>
                        <input type="date" name="trend_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </label>
                </p>

                <p>
                    <button class="button button-primary"><?php esc_html_e( 'Save Keyword', 'sk-blogger' ); ?></button>
                </p>
            </form>
        </div>

        <!-- Bulk Add -->
        <div class="sk-box">
            <h2><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sk_admin_action' ); ?>
                <input type="hidden" name="sk_action" value="bulk_trending">

                <p>
                    <label><?php esc_html_e( 'One keyword per line', 'sk-blogger' ); ?><br>
                        <textarea name="bulk_keywords" rows="8" class="large-text"
                                  placeholder="ChatGPT update 2026&#10;AI agents for developers&#10;Machine learning trends"></textarea>
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Category (for all)', 'sk-blogger' ); ?><br>
                        <?php wp_dropdown_categories( [
                            'name'              => 'category_id',
                            'hide_empty'        => 0,
                            'orderby'           => 'name',
                            'order'             => 'ASC',
                            'show_count'        => 1,
                            'hierarchical'      => 1,
                            'class'             => 'regular-text',
                            'show_option_none'  => __( '— Select Category —', 'sk-blogger' ),
                            'option_none_value' => '0',
                        ] ); ?>
                    </label>
                </p>

                <p>
                    <label><?php esc_html_e( 'Trend Date (for all)', 'sk-blogger' ); ?><br>
                        <input type="date" name="trend_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </label>
                </p>

                <p>
                    <button class="button button-primary"><?php esc_html_e( 'Bulk Add', 'sk-blogger' ); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- Manual Trigger -->
    <p style="margin:20px 0;">
        <form method="post" style="display:inline">
            <?php wp_nonce_field( 'sk_admin_action' ); ?>
            <input type="hidden" name="sk_action" value="run_trending_now">
            <button class="button button-primary"
                    onclick="return confirm('Run today\'s trending processing now?')">
                <?php esc_html_e( '▶ Run Trending Now', 'sk-blogger' ); ?>
            </button>
        </form>
    </p>

    <!-- Keywords List -->
    <h2><?php esc_html_e( 'Saved Keywords', 'sk-blogger' ); ?></h2>
    <?php if ( empty( $trending ) ) : ?>
        <p><em><?php esc_html_e( 'No keywords saved yet. Add trending keywords above.', 'sk-blogger' ); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th><?php esc_html_e( 'Keyword', 'sk-blogger' ); ?></th>
                    <th style="width:150px;"><?php esc_html_e( 'Category', 'sk-blogger' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Date', 'sk-blogger' ); ?></th>
                    <th style="width:90px;"><?php esc_html_e( 'Status', 'sk-blogger' ); ?></th>
                    <th style="width:70px;"><?php esc_html_e( 'Post', 'sk-blogger' ); ?></th>
                    <th style="width:70px;"><?php esc_html_e( 'Actions', 'sk-blogger' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $trending as $t ) :
                $cat = get_category( $t->category_id );
            ?>
                <tr>
                    <td><?php echo (int) $t->id; ?></td>
                    <td><?php echo esc_html( $t->keyword ); ?></td>
                    <td>
                        <?php if ( $cat && ! is_wp_error( $cat ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=category&tag_ID=' . $t->category_id ) ); ?>">
                                <?php echo esc_html( $cat->name ); ?>
                            </a>
                        <?php else : ?>
                            <em style="color:#999;"><?php esc_html_e( 'Deleted', 'sk-blogger' ); ?></em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $t->trend_date ); ?></td>
                    <td>
                        <span class="sk-status sk-<?php echo esc_attr( $t->status ); ?>">
                            <?php echo esc_html( $t->status ); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ( $t->post_id ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $t->post_id ) ); ?>">#<?php echo (int) $t->post_id; ?></a>
                        <?php else : ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Delete this keyword?');">
                            <?php wp_nonce_field( 'sk_admin_action' ); ?>
                            <input type="hidden" name="sk_action" value="delete_trending">
                            <input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
                            <button class="button button-small">×</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>