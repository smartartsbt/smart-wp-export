<?php
// templates/admin-table.php
if (!current_user_can('manage_options')) {
    status_header(403);
    wp_die(__('Admin access required', 'smartart-export'));
}

global $wpdb;

$tables = SWE_Core::get_all_tables();
$raw_source = $_REQUEST['data_source'] ?? '';

// Extract table name if prefixed with "table:"
if (strpos($raw_source, 'table:') === 0) {
    $name = substr($raw_source, strlen('table:'));
} else {
    echo '<p>' . esc_html__('No table selected.', 'smartart-export') . '</p>';
    return;
}

$wpn = wp_create_nonce('wp_rest');

// Pagination setup
$current_page = max(1, intval($_REQUEST['page_num'] ?? 1));

// Allowed per_page options (similar to post-type)
$allowed_per_pages = [5, 10, 20, 50, 100];
$per_page = intval($_REQUEST['per_page'] ?? 20);
if (!in_array($per_page, $allowed_per_pages)) {
    $per_page = 20;
}

$offset = ($current_page - 1) * $per_page;

$columns = SWE_Core::get_table_columns($name);
if (empty($columns)) {
    echo '<p>' . esc_html__('Failed to load table columns.', 'smartart-export') . '</p>';
    return;
}

$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$name`"));
$total_pages = max(1, ceil($total / $per_page));
//$data = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$name` LIMIT %d, %d", $offset, $per_page), ARRAY_A);

$start_date = sanitize_text_field($_REQUEST['start_date'] ?? '');
$end_date = sanitize_text_field($_REQUEST['end_date'] ?? '');

// Guess date column (basic version)
$date_column = '';
foreach (['created_at', 'created', 'date', 'comment_date', 'post_date', 'updated_at'] as $col_guess) {
    if (in_array($col_guess, $columns)) {
        $date_column = $col_guess;
        break;
    }
}

$where = '';
$params = [];

if ($date_column) {
    if ($start_date) {
        $where .= " AND `$date_column` >= %s";
        $params[] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $where .= " AND `$date_column` <= %s";
        $params[] = $end_date . ' 23:59:59';
    }
} else {
    // Clear any date filter if no valid date column is found
    $start_date = '';
    $end_date = '';
}

// Count with filter
$total = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM `$name` WHERE 1=1 $where",
    ...$params
));

$params[] = $offset;
$params[] = $per_page;

// Data with filter
$data = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM `$name` WHERE 1=1 $where LIMIT %d, %d",
    ...$params
), ARRAY_A);

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
            >&laquo; <?= esc_html__('Previous', 'smartart-export') ?></button>
        <?php endif; ?>

        <span class="paging-input">
            <?= sprintf(esc_html__('Page %d of %d', 'smartart-export'), $current_page, $total_pages) ?>
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
            ><?= esc_html__('Next', 'smartart-export') ?> &raquo;</button>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!-- Top controls flex container -->
<div class="tablenav_top">
    <button type="button" class="button button-primary" onclick="exportToCSV()">
        <?= esc_html__('Export to CSV', 'smartart-export') ?>
    </button>

     <label for="per_page_select">
        <?= esc_html__('Items per page:', 'smartart-export') ?>
        <select id="per_page_select" name="per_page" onchange="this.form.requestSubmit()">
            <?php foreach ($allowed_per_pages as $pp): ?>
                <option value="<?= esc_attr($pp) ?>" <?= selected($pp, $per_page, false) ?>><?= esc_html($pp) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

   <?php if ($start_date || $end_date): ?>
    <span style="margin-left: 10px; color: #666;">
        <?= $date_column
            ? sprintf(
                esc_html__('(Filtering by "%s")', 'smartart-export'),
                esc_html($date_column)
            )
            : esc_html__('(No matching date column found)', 'smartart-export')
        ?>
    </span>
<?php endif; ?>

<?php if (!$date_column && ($start_date || $end_date)): ?>
    <div style="margin-top: 4px; font-size: 11px; color: #999;">
        <?= esc_html__('Checked for date column in:', 'smartart-export') ?>
        <code><?= esc_html(implode(', ', $date_column_guesses)) ?></code>
    </div>
<?php endif; ?>


    <?php render_pagination($current_page, $total_pages, $wpn, $raw_source, $per_page); ?>
</div>

<form
    id="swe-form"
    hx-post="<?= esc_url(SWE_Core::$url) ?>"
    hx-target="#swe-results"
    hx-swap="innerHTML"
    hx-indicator="#swe-spinner"
    hx-include="#data_source"
>
    <input type="hidden" name="_wpnonce" value="<?= esc_attr($wpn) ?>" />
    <input type="hidden" name="data_source" value="<?= esc_attr($raw_source) ?>" />

   

    <?php if ($data): ?>
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
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                           <td><?= esc_html(crop_to_chars($row[$col] ?? '')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?= esc_html__('No data found in this table.', 'smartart-export') ?></p>
    <?php endif; ?>
</form>

<!-- Bottom pagination -->
<?php render_pagination($current_page, $total_pages, $wpn, $raw_source, $per_page); ?>

<script>
function exportToCSV() {
    var table = document.querySelector('input[name="data_source"]').value;
    if (!table) {
        alert('<?= esc_js(__('Please select a table to export.', 'smartart-export')) ?>');
        return;
    }

    var url = "<?= admin_url('admin-ajax.php') ?>";
    var formData = new FormData();

    formData.append('action', 'export_table_to_csv');
    formData.append('table', table);
    formData.append('_wpnonce', '<?= esc_attr($wpn) ?>');

    document.querySelectorAll('input[name="columns[]"]:checked').forEach(function(input) {
        formData.append('columns[]', input.value);
    });

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.blob())
    .then(blob => {
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = table + '.csv';
        link.click();
    })
    .catch(error => console.error('<?= esc_js(__('Error exporting table:', 'smartart-export')) ?>', error));
}
</script>
