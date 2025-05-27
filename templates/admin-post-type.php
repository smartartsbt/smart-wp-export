<?php
// templates/admin-post-type.php
if (!current_user_can('manage_options')) {
    status_header(403);
    wp_die(__('Admin access required', 'smart-wp-export'));
}

global $wpdb;

// Determine selected source
$raw_source = $_REQUEST['data_source'] ?? '';
if (strpos($raw_source, 'post_type:') === 0) {
    $name = substr($raw_source, strlen('post_type:'));
} else {
    echo '<p>' . esc_html__('No post type selected.', 'smart-wp-export') . '</p>';
    return;
}

$wpn = wp_create_nonce('wp_rest');

// Pagination setup
$current_page = max(1, intval($_REQUEST['page_num'] ?? 1));

// Get per_page from request or default to 20
$allowed_per_pages = [5, 10, 20, 50, 100];
$per_page = intval($_REQUEST['per_page'] ?? 20);
if (!in_array($per_page, $allowed_per_pages)) {
    $per_page = 20;
}

// Fetch post type columns (assuming $wpdb->posts table)
$columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->posts}");
if (empty($columns)) {
    echo '<p>' . esc_html__('Failed to load post columns.', 'smart-wp-export') . '</p>';
    return;
}

remove_all_filters('pre_get_posts');

// WP_Query for pagination
$args = [
    'post_type'      => $name,
    'posts_per_page' => $per_page,
    'paged'          => $current_page,
    'post_status'    => 'any',
    'no_found_rows'  => false,
];

$start_date = sanitize_text_field($_REQUEST['start_date'] ?? '');
$end_date = sanitize_text_field($_REQUEST['end_date'] ?? '');

if ($start_date || $end_date) {
    $args['date_query'] = [];
    if ($start_date) {
        $args['date_query'][] = ['after' => $start_date];
    }
    if ($end_date) {
        $args['date_query'][] = ['before' => $end_date];
    }
}

//$query = new WP_Query($args);
try {
    $query = new WP_Query($args);
} catch (Throwable $e) {
    error_log($e->getMessage());
    echo '<p>' . esc_html__('Error fetching posts. Please check your query.', 'smart-wp-export') . '</p>';
    return;
}

if (!$query->have_posts()) {
    echo '<p>' . esc_html__('No posts found for this post type.', 'smart-wp-export') . '</p>';
    return;
}

$total_pages = $query->max_num_pages;

function render_pagination($current_page, $total_pages, $wpn, $raw_source, $per_page) {
    ?>
    <div class="tablenav">
        <?php if ($current_page > 1): ?>
            <button class="button"
                hx-post="<?= esc_url(SWE_Core::$url) ?>"
                hx-vals='<?= esc_attr(json_encode([
                    "_wpnonce" => $wpn,
                    "data_source" => $raw_source,
                    "page_num" => $current_page - 1,
                    "per_page" => $per_page,
                    "start_date" => $_REQUEST['start_date'] ?? '',
                    "end_date" => $_REQUEST['end_date'] ?? '',
                ])) ?>'
                hx-target="#swe-results"
            >&laquo; <?= esc_html__('Previous', 'smart-wp-export') ?></button>
        <?php endif; ?>

        <span class="paging-input">
            <?= sprintf(esc_html__('Page %d of %d', 'smart-wp-export'), $current_page, $total_pages) ?>
        </span>

        <?php if ($current_page < $total_pages): ?>
            <button class="button"
                hx-post="<?= esc_url(SWE_Core::$url) ?>"
                hx-vals='<?= esc_attr(json_encode([
                    "_wpnonce" => $wpn,
                    "data_source" => $raw_source,
                    "page_num" => $current_page + 1,
                    "per_page" => $per_page,
                    "start_date" => $_REQUEST['start_date'] ?? '',
                    "end_date" => $_REQUEST['end_date'] ?? '',
                ])) ?>'
                hx-target="#swe-results"
            ><?= esc_html__('Next', 'smart-wp-export') ?> &raquo;</button>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!-- Top controls flex container -->
<div class="tablenav_top" >
    <button type="button" class="button button-primary" onclick="exportToCSV()">
        <?= esc_html__('Export to CSV', 'smart-wp-export') ?>
    </button>

     <label for="per_page_select">
        <?= esc_html__('Items per page:', 'smart-wp-export') ?>
        <select id="per_page_select" name="per_page" onchange="this.form.requestSubmit()">
            <?php foreach ($allowed_per_pages as $pp): ?>
                <option value="<?= esc_attr($pp) ?>" <?= selected($pp, $per_page, false) ?>><?= esc_html($pp) ?></option>
            <?php endforeach; ?>
        </select>
    </label>


    
       

       <?php if ($start_date || $end_date): ?>
        <span style="margin-left: 10px; color: #666;">
            <?= esc_html__('(Filtering by post date)', 'smart-wp-export') ?>
        </span>
    <?php endif; ?>
    
    <?php render_pagination($current_page, $total_pages, $wpn, $raw_source, $per_page); ?>
</div>

<form
    id="swe-form"
    hx-include="#data_source"
    hx-post="<?= esc_url(SWE_Core::$url) ?>"
    hx-target="#swe-results"
    hx-swap="innerHTML"
    hx-indicator="#swe-spinner"
>
    <input type="hidden" name="_wpnonce" value="<?= esc_attr($wpn) ?>" />
    <input type="hidden" name="data_source" value="<?= esc_attr($raw_source) ?>" />

   

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th>
                        <label>
                            <input type="checkbox" name="columns[]" value="<?= esc_attr($col) ?>" checked>
                            <?= esc_html($col) ?>
                        </label>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($query->have_posts()): $query->the_post(); $post = get_post(); ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td><?= esc_html($post->$col ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
    </table>
</form>

<!-- Bottom pagination -->
<?php render_pagination($current_page, $total_pages, $wpn, $raw_source, $per_page); ?>

<script>
function exportToCSV() {
    var table = document.querySelector('input[name="data_source"]').value;
    if (!table) {
        alert('<?= esc_js(__('Please select a source to export.', 'smart-wp-export')) ?>');
        return;
    }

    var url = "<?= admin_url('admin-ajax.php') ?>";
    var formData = new FormData();
    formData.append('action', 'export_table_to_csv');
    formData.append('table', table);
    formData.append('_wpnonce', '<?= esc_attr($wpn) ?>');
    document.querySelectorAll('input[name="columns[]"]:checked').forEach(function(i) { formData.append('columns[]', i.value); });

    fetch(url, { method: 'POST', body: formData })
        .then(r => r.blob())
        .then(b => {
            var l = document.createElement('a');
            l.href = URL.createObjectURL(b);
            l.download = table + '.csv';
            l.click();
        })
        .catch(e => console.error('<?= esc_js(__('Error exporting:', 'smart-wp-export')) ?>', e));
}
</script>
