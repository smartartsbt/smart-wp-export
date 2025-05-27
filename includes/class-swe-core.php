<?php

class SWE_Core {
    public static $url;


    public static function init() {
        // Use the 'init' action hook to make sure WordPress is fully initialized
        add_action('init', function() {
            // Ensure that the REST URL is only generated after WordPress has fully initialized
            self::$url = esc_url_raw(rest_url('smart-export/v1/table'));
        });

        

    }
    

    public static function get_all_tables() {
        global $wpdb;
        return $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
    }

    public static function get_table_columns($table) {
        global $wpdb;
        return $wpdb->get_col("DESC `$table`", 0);
    }

    public static function render_admin_page() {
       include SWE_PATH . 'templates/admin-form.php';
    }

    public static function handle_request() {
    if (!current_user_can('manage_options')) {
        wp_die("Unauthorized", "403", ['response' => 403]);
    }

    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'wp_rest')) {
        wp_die('Invalid nonce', 'Error', ['response' => 403]);
    }

    $source = $_REQUEST['data_source'] ?? '';

    if (empty($source)) {
        echo '<p>' . esc_html__('No data source selected.', 'smartart-export') . '</p>';
        exit;
    }

    // Split "table:wp_users" or "post_type:post"
    if (strpos($source, ':') !== false) {
        list($type, $name) = explode(':', $source, 2);
    } else {
        $type = 'table';
        $name = $source;
    }

    if ($type === 'post_type') {
        include SWE_PATH . 'templates/admin-post-type.php';
    } elseif ($type === 'table') {
        include SWE_PATH . 'templates/admin-table.php';
    } else {
        echo '<p>' . esc_html__('Invalid source type.', 'smartart-export') . '</p>';
    }

    exit;
}



    public static function render_table_data($table) {
        global $wpdb;

        $columns = self::get_table_columns($table);
        $data = $wpdb->get_results("SELECT * FROM `$table` LIMIT 100", ARRAY_A);

        include SWE_PATH . 'templates/admin-table-data.php'; // you can create separate templates or embed rendering here
    }

    public static function render_post_type_data($post_type) {
        $columns = ['ID', 'post_title', 'post_date', 'post_status']; // customize as needed
        $posts = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => 100,
        ]);

        include SWE_PATH . 'templates/admin-post-type-data.php'; // create this template for posts display
    }

    

    public static function export_table($table) {
        global $wpdb;

        // Validate table exists etc. here if needed

        $columns = $_POST['columns'] ?? [];
        $all_data = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);

        if (empty($all_data)) {
            wp_die('No data found to export.');
        }

        $filtered_data = [];
        if (!empty($columns)) {
            foreach ($all_data as $row) {
                $filtered_row = [];
                foreach ($columns as $col) {
                    $filtered_row[$col] = $row[$col] ?? '';
                }
                $filtered_data[] = $filtered_row;
            }
        } else {
            $filtered_data = $all_data;
        }

        $filename = $table . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');

        fputcsv($output, array_keys($filtered_data[0]));
        foreach ($filtered_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public static function export_post_type($post_type) {
        $columns = $_POST['columns'] ?? [];
        if (empty($columns)) {
            // Provide default columns if none selected
            $columns = ['ID', 'post_title', 'post_date', 'post_status'];
        }

        $posts = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => -1,
        ]);

        if (empty($posts)) {
            wp_die('No posts found to export.');
        }

        $filename = $post_type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, $columns);

        foreach ($posts as $post) {
            $row = [];
            foreach ($columns as $col) {
                // Safely get property if exists, else empty string
                $row[] = isset($post->$col) ? $post->$col : '';
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }


   

}