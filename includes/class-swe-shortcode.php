<?php
// includes/class-swe-shortcode.php

class SWE_Shortcode_Manager {
    const TABLE_NAME = 'swe_shortcodes';

    public static function init() {
        add_action( 'admin_menu',   [ self::class, 'register_admin_menu' ], 11 );
        add_action( 'admin_init',   [ self::class, 'handle_form_submit' ] );
        add_shortcode( 'smart_export_viewer', [ self::class, 'render_shortcode' ] );
        add_action('wp_ajax_swe_load_sources', [ self::class, 'swe_load_sources_ajax_handler' ]);

       add_action('wp_ajax_swe_get_columns', [ self::class,'swe_get_columns_ajax_handler']);

        register_activation_hook( SWE_FILE, [ self::class, 'create_table' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            shortcode_id varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            data_type varchar(20) NOT NULL,
            source_name varchar(255) NOT NULL,
            columns text NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY shortcode_id (shortcode_id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function register_admin_menu() {
        add_submenu_page(
            'smart-wp-export',
            __( 'Shortcode Viewer Settings', 'smartart-export' ),
            __( 'Shortcodes', 'smartart-export' ),
            'manage_options',
            'swe-shortcode-configs',
            [ self::class, 'render_admin_page' ]
        );
    }

    public static function handle_form_submit() {
        global $wpdb;

        // Delete
        if ( isset( $_GET['delete_id'] ) && current_user_can( 'manage_options' )
            && check_admin_referer( 'swe_delete_' . intval( $_GET['delete_id'] ) ) ) {
            $table = $wpdb->prefix . self::TABLE_NAME;
            $wpdb->delete( $table, [ 'id' => intval( $_GET['delete_id'] ) ] );
            wp_redirect( admin_url( 'admin.php?page=swe-shortcode-configs&deleted=1' ) );
            exit;
        }

        // Add / update
        if ( ! isset( $_POST['swe_shortcode_submit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'swe_shortcode_save' ) ) {
            return;
        }

        $table        = $wpdb->prefix . self::TABLE_NAME;
        $shortcode_id = sanitize_key( $_POST['shortcode_id'] );
        $title        = sanitize_text_field( $_POST['title'] );
        $type         = sanitize_key( $_POST['type'] );
        $source       = sanitize_text_field( $_POST['source'] );
        $columns      = array_map( 'trim', explode( ',', sanitize_text_field( $_POST['columns'] ) ) );
        $edit_id      = intval( $_POST['edit_id'] ?? 0 );

        if ( $edit_id ) {
            $wpdb->update(
                $table,
                [
                    'shortcode_id' => $shortcode_id,
                    'title'        => $title,
                    'data_type'    => $type,
                    'source_name'  => $source,
                    'columns'      => maybe_serialize( $columns ),
                ],
                [ 'id' => $edit_id ]
            );
        } else {
            $wpdb->replace(
                $table,
                [
                    'shortcode_id' => $shortcode_id,
                    'title'        => $title,
                    'data_type'    => $type,
                    'source_name'  => $source,
                    'columns'      => maybe_serialize( $columns ),
                ]
            );
        }

        wp_redirect( admin_url( 'admin.php?page=swe-shortcode-configs&saved=1' ) );
        exit;
    }

    public static function render_admin_page() {
        include SWE_PATH . 'templates/shortcode-admin.php';
    }

    public static function swe_load_sources_and_columns() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
        wp_die();
    }

    $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : 'table';
    $source_name = ''; // Can optionally retrieve from $_REQUEST

    $source_dropdown = swe_render_source_dropdown($type, $source_name);
    $columns_output = '<textarea id="columns" name="columns" class="large-text" cols="10" rows="3"></textarea>';
    $columns_output .= '<p class="description">Comma-separated list of column names to display</p>';

    echo '<div id="source-container">' . $source_dropdown . '</div>';
    echo '<div id="columns-container">' . $columns_output . '</div>';

    wp_die();
}
public static function swe_get_columns_ajax_handler() {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Unauthorized', 403);
        wp_die();
    }

    $type   = sanitize_text_field( $_REQUEST['type'] ?? 'table' );
    $source = sanitize_text_field( $_REQUEST['source'] ?? '' );

    global $wpdb;
    $columns = [];

    if ( $type === 'table' ) {
        // Check if table exists before querying
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $source
        ) );

        if ( ! $table_exists ) {
            echo '<p class="description">Table "' . esc_html($source) . '" does not exist.</p>';
            wp_die();
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$source`" );

    } elseif ( $type === 'post_type' ) {
        // Always get columns from posts table
        $post_table = $wpdb->posts;
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $post_table" );
    } else {
        echo '<p class="description">Invalid type parameter.</p>';
        wp_die();
    }

    if ( $wpdb->last_error ) {
        echo '<p class="description">SQL error: ' . esc_html( $wpdb->last_error ) . '</p>';
    } elseif ( empty( $columns ) ) {
        echo '<p class="description">No columns found. Source: ' . esc_html( $source ) . '</p>';
    } else {
        echo '<p>' . esc_html( implode(', ', $columns) ) . '</p>';
    }

    wp_die();
}








/*
public static function swe_get_columns_ajax_handler() {

    error_log('swe_get_columns_ajax_handler called');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
        wp_die();
    }

    

    global $wpdb;

    $source = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';
    if (!$source) {
        echo '';  // Just empty string if no source
        wp_die();
    }

    $columns = $wpdb->get_col("SHOW COLUMNS FROM `$source`");
    if (empty($columns)) {
        echo '<p class="description">' . esc_html__('No columns found or invalid table.', 'smartart-export') . '</p>';
        wp_die();
    }

    // Output the columns as comma-separated plain text wrapped in a div or p
    echo '<p>' . esc_html(implode(', ', $columns)) . '</p>';

    wp_die();
}*/


 public static function swe_load_sources_ajax_handler() {
    // Permissions check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
        wp_die();
    }

    // Grab the requested type (default to 'table')
    $type = isset( $_REQUEST['type'] ) 
          ? sanitize_text_field( $_REQUEST['type'] ) 
          : 'table';

    // Make sure the dropdown function exists
    if ( ! function_exists( 'swe_render_source_dropdown' ) ) {
        wp_send_json_error( 'Missing swe_render_source_dropdown()', 500 );
        wp_die();
    }

    // Echo the <select> HTML
    echo swe_render_source_dropdown( $type, '' );

    wp_die();
}


    private static function render_pagination( $current, $total, $id, $per_page ) {
        // strip any existing paging/per_page/id parameters
        $base = esc_url( remove_query_arg( [ 'paged', 'page', 'per_page', 'id' ], $_SERVER['REQUEST_URI'] ) );

        echo '<div class="swe-pagination" >';

        if ( $current > 1 ) {
            echo '<a class="button" href="'
              . esc_url( add_query_arg( [
                    'id'       => $id,
                    'paged'    => $current - 1,
                    'per_page' => $per_page,
                ], $base ) )
              . '">&laquo; Previous</a>';
        }

        echo '<span style="align-self:center;">'
           . sprintf(
                esc_html__( 'Page %1$d of %2$d', 'smartart-export' ),
                $current,
                $total
            )
           . '</span>';

        if ( $current < $total ) {
            echo '<a class="button" href="'
              . esc_url( add_query_arg( [
                    'id'       => $id,
                    'paged'    => $current + 1,
                    'per_page' => $per_page,
                ], $base ) )
              . '">Next &raquo;</a>';
        }

        echo '</div>';
    }

        public static function render_shortcode( $atts ) {
        global $wpdb;

        $atts = shortcode_atts( [ 'id' => '' ], $atts, 'smart_export_viewer' );
        $id   = sanitize_key( $atts['id'] );
        if ( ! $id ) {
            return '<p>' . esc_html__( 'Shortcode ID missing.', 'smartart-export' ) . '</p>';
        }

        // properly parenthesized nested ternary:
        $current = max(
            1,
            // if `paged` query var present, use it
            get_query_var( 'paged' )
                ? intval( get_query_var( 'paged' ) )
                // else if pretty `page` var present, use that
                : ( get_query_var( 'page' )
                    ? intval( get_query_var( 'page' ) )
                    // otherwise fallback to $_GET['page']
                    : intval( $_GET['page'] ?? 1 )
                )
        );

        $allowed_pp = [ 5, 10, 20, 50, 100 ];
        $per_page   = intval( $_GET['per_page'] ?? 20 );
        if ( ! in_array( $per_page, $allowed_pp, true ) ) {
            $per_page = 20;
        }
        $offset = ( $current - 1 ) * $per_page;

  

        // load config
        $table = $wpdb->prefix . self::TABLE_NAME;
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE shortcode_id = %s", $id )
        );
        if ( ! $row ) {
            return '<p>' . esc_html__( 'Invalid shortcode ID.', 'smartart-export' ) . '</p>';
        }

        $columns = maybe_unserialize( $row->columns );
        if ( empty( $columns ) || ! is_array( $columns ) ) {
            return '<p>' . esc_html__( 'No columns defined.', 'smartart-export' ) . '</p>';
        }

        // fetch data + count total pages
        if ( $row->data_type === 'table' ) {
            $count       = $wpdb->get_var( "SELECT COUNT(*) FROM `{$row->source_name}`" );
            $total_pages = max( 1, ceil( $count / $per_page ) );
            $data        = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$row->source_name}` LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $q           = new WP_Query( [
                'post_type'      => $row->source_name,
                'posts_per_page' => $per_page,
                'paged'          => $current,
                'post_status'    => 'any',
                'no_found_rows'  => false,
            ] );
            $count       = $q->found_posts;
            $total_pages = max( 1, ceil( $count / $per_page ) );
            $data        = $q->posts;
        }

        ob_start();

        // title
        if ( $row->title ) {
            echo '<h3>' . esc_html( $row->title ) . '</h3>';
        }

        //Top controls flex container -->
         echo '<div class="tablenav_top">';

                // per-page selector
                echo '<form method="get">';
                echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
                echo '<label>' . esc_html__( 'Items per page:', 'smartart-export' );
                echo '<select name="per_page" onchange="this.form.submit()">';
                foreach ( $allowed_pp as $pp ) {
                    printf(
                        '<option value="%1$d"%2$s>%1$d</option>',
                        $pp,
                        selected( $pp, $per_page, false )
                    );
                }
                echo '</select></label>';
                echo '</form>';

                // top pagination
                self::render_pagination( $current, $total_pages, $id, $per_page );
            echo '</div>';

        // table
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ( $columns as $col ) {
            echo '<th>' . esc_html( $col ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( $data ) {
            foreach ( $data as $row_item ) {
                echo '<tr>';
                foreach ( $columns as $c ) {
                    $val = is_array( $row_item ) ? ( $row_item[ $c ] ?? '' ) : ( $row_item->$c ?? '' );
                    echo '<td>' . esc_html( $val ) . '</td>';
                }
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="' . count( $columns ) . '">'
               . esc_html__( 'No items found.', 'smartart-export' )
               . '</td></tr>';
        }

        echo '</tbody></table>';

        // bottom pagination
        self::render_pagination( $current, $total_pages, $id, $per_page );

        return ob_get_clean();
    }

}

SWE_Shortcode_Manager::init();
