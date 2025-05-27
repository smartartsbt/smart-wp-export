<?php
if (!current_user_can('manage_options')) {
    status_header(403);
    wp_die("Admin access required");
}

global $wpdb;
// Get all public post types except attachments
$post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
$builtin_post_types = get_post_types(['public' => true, '_builtin' => true], 'objects');
$all_post_types = array_merge($builtin_post_types, $post_types);

$tables = SWE_Core::get_all_tables();
$current = $_REQUEST['data_source'] ?? '';
$wpn = wp_create_nonce('wp_rest');
?>

<h1><?php echo esc_html__('SmartArt WP Export', 'smart-wp-export'); ?></h1>

<form hx-vals='{"_wpnonce": "<?= esc_attr($wpn) ?>"}'
      hx-target="#swe-results"
      hx-post="<?= SWE_Core::$url ?>"
      hx-swap="innerHTML"
      hx-indicator="#swe-spinner">

    <label for="data_source"><?php echo esc_html__('Select a data source to export:', 'smart-wp-export'); ?></label>
    <select id="data_source" name="data_source" 
        hx-trigger="change" 
        hx-target="#swe-results" 
        hx-post="<?= SWE_Core::$url ?>"
        hx-indicator="#swe-spinner">
    <option value="">--</option>

    <optgroup label="<?php esc_attr_e('Database Tables', 'smart-wp-export'); ?>">
        <?php foreach ($tables as $table): ?>
            <option value="table:<?= esc_attr($table) ?>" <?= selected($current, "table:$table", false) ?>>
                <?= esc_html($table) ?>
            </option>
        <?php endforeach; ?>
    </optgroup>

    <optgroup label="<?php esc_attr_e('Post Types', 'smart-wp-export'); ?>">
        <?php foreach (get_post_types(['show_ui' => true], 'names') as $post_type): ?>
            <option value="post_type:<?= esc_attr($post_type) ?>" <?= selected($current, "post_type:$post_type", false) ?>>
                <?= esc_html($post_type) ?>
            </option>
        <?php endforeach; ?>
    </optgroup>
</select>


<label for="start_date"><?php _e('Start Date:', 'smart-wp-export'); ?></label>
<input type="date" id="start_date" name="start_date" 
       hx-trigger="change" 
       hx-target="#swe-results"
       hx-post="<?= SWE_Core::$url ?>"
       hx-include="[name='data_source'], [name='end_date']"
       hx-indicator="#swe-spinner">

<label for="end_date"><?php _e('End Date:', 'smart-wp-export'); ?></label>
<input type="date" id="end_date" name="end_date"
       hx-trigger="change" 
       hx-target="#swe-results"
       hx-post="<?= SWE_Core::$url ?>"
       hx-include="[name='data_source'], [name='start_date']"
       hx-indicator="#swe-spinner">



    <span id="swe-spinner" class="htmx-indicator"><?php echo esc_html__('Loading...', 'smart-wp-export'); ?></span>
    <div id="swe-results"></div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof htmx !== 'undefined') {
            console.log('HTMX is loaded and ready');
        } else {
            console.error('HTMX is not loaded');
        }
    });
</script>