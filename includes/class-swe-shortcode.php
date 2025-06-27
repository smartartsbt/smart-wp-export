<?php
// includes/class-swe-shortcode.php

class SWE_Shortcode_Manager {
    const TABLE_NAME = 'swe_shortcodes';

    public static function init() {
        add_action( 'admin_menu',   [ self::class, 'register_admin_menu' ], 11 );
        add_action( 'admin_init',   [ self::class, 'handle_form_submit' ] );
        add_shortcode( 'smart_export_viewer', [ self::class, 'render_shortcode' ] );

        add_action( 'wp_ajax_swe_load_sources',      [ self::class, 'swe_load_sources_ajax_handler' ] );
        add_action( 'wp_ajax_swe_get_columns',       [ self::class, 'swe_get_columns_ajax_handler' ] );

        register_activation_hook( SWE_FILE, [ self::class, 'create_table' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
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
            __( 'Shortcode Viewer Settings', 'smart-wp-export' ),
            __( 'Shortcodes', 'smart-wp-export' ),
            'manage_options',
            'swe-shortcode-configs',
            [ self::class, 'render_admin_page' ]
        );
    }

    public static function handle_form_submit() {
        global $wpdb;

        if ( isset( $_GET['delete_id'] ) && current_user_can( 'manage_options' )
            && check_admin_referer( 'swe_delete_' . intval( $_GET['delete_id'] ) ) ) {
            $wpdb->delete( $wpdb->prefix . self::TABLE_NAME, [ 'id' => intval( $_GET['delete_id'] ) ] );
            wp_redirect( admin_url( 'admin.php?page=swe-shortcode-configs&deleted=1' ) );
            exit;
        }

        if ( ! isset( $_POST['swe_shortcode_submit'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'swe_shortcode_save' ) ) return;

        $table        = $wpdb->prefix . self::TABLE_NAME;
        $shortcode_id = sanitize_key( $_POST['shortcode_id'] );
        $title        = sanitize_text_field( $_POST['title'] );
        $type         = sanitize_key( $_POST['type'] );
        $source       = sanitize_text_field( $_POST['source'] );
        $columns      = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $_POST['columns'] ) ) );
        $edit_id      = intval( $_POST['edit_id'] ?? 0 );

        $data = [
            'shortcode_id' => $shortcode_id,
            'title'        => $title,
            'data_type'    => $type,
            'source_name'  => $source,
            'columns'      => maybe_serialize( $columns ),
        ];

        if ( $edit_id ) {
            $wpdb->update( $table, $data, [ 'id' => $edit_id ] );
        } else {
            $wpdb->replace( $table, $data );
        }

        wp_redirect( admin_url( 'admin.php?page=swe-shortcode-configs&saved=1' ) );
        exit;
    }

    public static function render_admin_page() {
        include SWE_PATH . 'templates/shortcode-admin.php';
    }

    public static function swe_load_sources_ajax_handler() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
            wp_die();
        }

        $type = isset( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : 'table';

        if ( ! function_exists( 'swe_render_source_dropdown' ) ) {
            wp_send_json_error( 'Missing swe_render_source_dropdown()', 500 );
            wp_die();
        }

        echo swe_render_source_dropdown( $type, '' );
        wp_die();
    }

    public static function swe_get_columns_ajax_handler() {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized', 403);
            wp_die();
        }

        global $wpdb;

        $type   = sanitize_text_field( $_REQUEST['type'] ?? 'table' );
        $source = sanitize_text_field( $_REQUEST['source'] ?? '' );

        $columns = [];

        if ( $type === 'table' ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $source ) );

            if ( ! $table_exists ) {
                echo '<p class="description">Table "' . esc_html($source) . '" does not exist.</p>';
                wp_die();
            }

            $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$source`" );

        } elseif ( $type === 'post_type' ) {
            $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->posts}" );
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

    private static function render_pagination( $current, $total, $id, $per_page ) {
        

        $request_uri = sanitize_url( $_SERVER['REQUEST_URI'] ?? '' );
        $base = esc_url( remove_query_arg( [ 'paged', 'page', 'per_page', 'id' ], $request_uri ) );


        echo '<div class="swe-pagination">';

        if ( $current > 1 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( [
                'id' => $id,
                'paged' => $current - 1,
                'per_page' => $per_page,
            ], $base ) ) . '">&laquo; Previous</a>';
        }

        echo '<span style="align-self:center;">' . sprintf( esc_html__( 'Page %1$d of %2$d', 'smart-wp-export' ), $current, $total ) . '</span>';

        if ( $current < $total ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( [
                'id' => $id,
                'paged' => $current + 1,
                'per_page' => $per_page,
            ], $base ) ) . '">Next &raquo;</a>';
        }

        echo '</div>';
    }

    public static function render_shortcode( $atts ) {
        global $wpdb;

        $atts = shortcode_atts( [ 'id' => '' ], $atts, 'smart_export_viewer' );
        $id   = sanitize_key( $atts['id'] );
        if ( ! $id ) return '<p>' . esc_html__( 'Shortcode ID missing.', 'smart-wp-export' ) . '</p>';

        //$current = max( 1, intval( $_GET['paged'] ?? $_GET['page'] ?? 1 ) );
        $paged_query = get_query_var( 'paged' );
        $current = max( 1, intval( $paged_query ? $paged_query : ( $_GET['paged'] ?? $_GET['page'] ?? 1 ) ) );


        $allowed_pp = [ 5, 10, 20, 50, 100 ];
        $per_page   = intval( $_GET['per_page'] ?? 20 );
        if ( ! in_array( $per_page, $allowed_pp, true ) ) $per_page = 20;
        $offset = ( $current - 1 ) * $per_page;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_NAME . " WHERE shortcode_id = %s", $id )
        );

        if ( ! $row ) return '<p>' . esc_html__( 'Invalid shortcode ID.', 'smart-wp-export' ) . '</p>';

        $columns = maybe_unserialize( $row->columns );
        if ( empty( $columns ) || ! is_array( $columns ) ) {
            return '<p>' . esc_html__( 'No columns defined.', 'smart-wp-export' ) . '</p>';
        }

        if ( $row->data_type === 'table' ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$row->source_name}`" );
            $total_pages = max( 1, ceil( $count / $per_page ) );
            $data = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM `{$row->source_name}` LIMIT %d OFFSET %d", $per_page, $offset ),
                ARRAY_A
            );
        } else {
            $q = new WP_Query( [
                'post_type' => $row->source_name,
                'posts_per_page' => $per_page,
                'paged' => $current,
                'post_status' => 'any',
                'no_found_rows' => false,
            ] );
            $count = $q->found_posts;
            $total_pages = max( 1, ceil( $count / $per_page ) );
            $data = $q->posts;
        }

        ob_start();

        if ( $row->title ) {
            echo '<h3>' . esc_html( $row->title ) . '</h3>';
        }

        echo '<div class="tablenav_top">';
        echo '<form method="get">';
        echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
        echo '<label>' . esc_html__( 'Items per page:', 'smart-wp-export' );
        echo '<select name="per_page" onchange="this.form.submit()">';
        foreach ( $allowed_pp as $pp ) {
            printf( '<option value="%1$d"%2$s>%1$d</option>', $pp, selected( $pp, $per_page, false ) );
        }
        echo '</select></label></form>';
        self::render_pagination( $current, $total_pages, $id, $per_page );
        echo '</div>';

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
            echo '<tr><td colspan="' . count( $columns ) . '">' . esc_html__( 'No items found.', 'smart-wp-export' ) . '</td></tr>';
        }

        echo '</tbody></table>';
        self::render_pagination( $current, $total_pages, $id, $per_page );

        return ob_get_clean();
    }
}

SWE_Shortcode_Manager::init();
