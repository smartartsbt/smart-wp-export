<?php


add_action('wp_ajax_export_table_to_csv', 'export_table_to_csv_callback');

function export_table_to_csv_callback() {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_rest')) {
        wp_die('Invalid nonce', 'Error', ['response' => 403]);
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', '403', ['response' => 403]);
    }

    $raw_source = $_POST['table'] ?? '';
    $columns = $_POST['columns'] ?? [];

    if (!$raw_source) {
        wp_die('No table or post type selected.', 'Error', ['response' => 400]);
    }

    // Determine source type
    if (strpos($raw_source, 'table:') === 0) {
        $table = substr($raw_source, strlen('table:'));
        export_table_data($table, $columns);
    } elseif (strpos($raw_source, 'post_type:') === 0) {
        $post_type = substr($raw_source, strlen('post_type:'));
        export_post_type_data($post_type, $columns);
    } else {
        wp_die('Invalid data source format.');
    }
}

// Table export
function export_table_data($table, $columns) {
    global $wpdb;

    $all_data = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
    if (empty($all_data)) {
        wp_die('No data found in the selected table.');
    }

    // Filter selected columns
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

// Get data for Source dropdown
function swe_render_source_dropdown( $type = 'table', $selected = '' ) {
        global $wpdb;

        if ( $type === 'table' ) {
            $options = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
        } else {
            $options = get_post_types( [ 'public' => true ], 'names' );
            
        }

       ob_start();
    ?>
    <select id="source" name="source"
    hx-get="<?php echo admin_url('admin-ajax.php?action=swe_get_columns'); ?>"
    hx-trigger="change"
    hx-target="#columns-list"
    hx-include="#source,#type">
    <?php foreach ( $options as $opt ) : ?>
        <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $selected, $opt ); ?>>
            <?php echo esc_html( $opt ); ?>
        </option>
    <?php endforeach; ?>
</select>
    <?php
    return ob_get_clean();
}

// Crop content
function crop_to_words(string $text, int $max_words = 10): string {
    $words = preg_split('/\s+/', trim($text));
    if (count($words) <= $max_words) {
        return $text;
    }
    $cropped = array_slice($words, 0, $max_words);
    return implode(' ', $cropped) . '...';
}
// Crop content
function crop_to_chars(string $text, int $max_chars = 50): string {
    $text = trim($text);
    if (mb_strlen($text) <= $max_chars) {
        return $text;
    }
    return mb_substr($text, 0, $max_chars) . '...';
}

// Post type export
function export_post_type_data($post_type, $columns) {
    if (empty($columns)) {
        $columns = ['ID', 'post_title', 'post_date', 'post_status'];
    }

    $posts = get_posts([
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);

    if (empty($posts)) {
        wp_die('No posts found.');
    }

    $filename = $post_type . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    fputcsv($output, $columns);

    foreach ($posts as $post) {
        $row = [];
        foreach ($columns as $col) {
            $row[] = isset($post->$col) ? $post->$col : '';
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
