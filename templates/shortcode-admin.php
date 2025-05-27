<?php
// templates/shortcode-admin.php
if (!current_user_can('manage_options')) {
    wp_die(__('Access denied', 'smartart-export'));
}



// Handle saved flag
$saved = isset($_GET['saved']);

// Fetch existing configs
global $wpdb;
$table_name = $wpdb->prefix . SWE_Shortcode_Manager::TABLE_NAME;
$configs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");


$edit_config = null;
if (isset($_GET['edit_id'])) {
    $edit_config = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['edit_id'])));
}

?>
<div class="wrap">
    <h1><?php esc_html_e('Shortcode Viewer Settings', 'smartart-export'); ?></h1>
    <?php if ($saved): ?>
        <div id="message" class="updated notice is-dismissible"><p><?php esc_html_e('Configuration saved.', 'smartart-export'); ?></p></div>
    <?php endif; ?>
    
    <?php if ( isset( $_GET['deleted'] ) ): ?>
        <div id="message" class="updated notice is-dismissible">
            <p><?php esc_html_e( 'Configuration deleted.', 'smartart-export' ); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php esc_html_e('Existing Configurations', 'smartart-export'); ?></h2>
    <table class="widefat fixed striped">
      <thead><tr>
    <th><?php esc_html_e('Shortcode ID', 'smartart-export'); ?></th>
    <th><?php esc_html_e('Title', 'smartart-export'); ?></th>
    <th><?php esc_html_e('Type', 'smartart-export'); ?></th>
    <th><?php esc_html_e('Source', 'smartart-export'); ?></th>
    <th><?php esc_html_e('Columns', 'smartart-export'); ?></th>
    <th><?php esc_html_e('Shortcode', 'smartart-export'); ?></th>
</tr></thead>
<tbody>

        <?php if ($configs): ?>
            <?php foreach ($configs as $cfg): ?>
                <?php $cols = maybe_unserialize($cfg->columns); ?>
                <tr>
                    <td><?php echo esc_html($cfg->shortcode_id); ?></td>
                    <td><?php echo esc_html($cfg->title); ?></td>
                    <td><?php echo esc_html($cfg->data_type); ?></td>
                    <td><?php echo esc_html($cfg->source_name); ?></td>
                    <td><?php echo esc_html(is_array($cols) ? implode(', ', $cols) : ''); ?></td>
                    <td>
                        <?php $shortcode = '[smart_export_viewer id="' . esc_attr($cfg->shortcode_id) . '"]'; ?>
                        <input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" style="width: 80%;" onclick="this.select();" /><br />
                        <button class="button button-small button-primary" onclick="copyToClipboard(this)">Copy</button>
                        <a href="<?php echo admin_url('admin.php?page=swe-shortcode-configs&edit_id=' . intval($cfg->id)); ?>" class="button button-small">Edit</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=swe-shortcode-configs&delete_id=' . intval($cfg->id)), 'swe_delete_' . intval($cfg->id)); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this configuration?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5"><?php esc_html_e('No configurations found.', 'smartart-export'); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

  <h2> <?php echo $edit_config ? esc_html__('Edit Configuration', 'smartart-export') : esc_html__('Add New Configuration', 'smartart-export'); ?></h2>
    <form id="my-form" method="post">
        <?php if ($edit_config): ?>
            <input type="hidden" name="edit_id" value="<?php echo intval($edit_config->id); ?>">
        <?php endif; ?>

        <?php wp_nonce_field('swe_shortcode_save'); ?>
        <table class="form-table">
            <tr>
                <th><label for="shortcode_id"><?php esc_html_e('Shortcode ID', 'smartart-export'); ?></label></th>
                <td><input name="shortcode_id" type="text" id="shortcode_id" value="<?php echo esc_attr($edit_config->shortcode_id ?? ''); ?>" class="regular-text" required />
                <p class="description"><?php esc_html_e('Unique ID, used in [smart_export_viewer id="..."]', 'smartart-export'); ?></p></td>
            </tr>
            <tr>
                <th><label for="title"><?php esc_html_e('Title', 'smartart-export'); ?></label></th>
                <td><input name="title" type="text" id="title" value="<?php echo esc_attr($edit_config->title ?? ''); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Optional title above the table', 'smartart-export'); ?></p></td>
            </tr>
           <tr>
  <th><label for="type">Data Type</label></th>
  <td>
    <select id="type" name="type"
      hx-get="<?php echo admin_url('admin-ajax.php?action=swe_load_sources'); ?>"
      hx-trigger="change"
      hx-target="#source-container"
      hx-swap="innerHTML"
    >
      <option value="table" <?php selected($edit_config->data_type ?? '', 'table'); ?>>Table</option>
      <option value="post_type" <?php selected($edit_config->data_type ?? '', 'post_type'); ?>>Post Type</option>
    </select>
  </td>
</tr>
            <tr>
  <th><label for="source">Source Name</label></th>
  <td>
    <div id="source-container"
         hx-get="<?php echo admin_url('admin-ajax.php?action=swe_load_sources'); ?>"
         hx-trigger="load, change from:#type"
         hx-target="#source-container"
         hx-swap="innerHTML"
         hx-include="#type">
      <?php echo swe_render_source_dropdown($edit_config->data_type ?? 'table', $edit_config->source_name ?? ''); ?>
    </div>
  </td>
</tr>
<tr>
  <th><label>Columns</label></th>
  <td>
   <div id="columns-container"
     hx-get="<?php echo admin_url('admin-ajax.php?action=swe_get_columns'); ?>"
     hx-trigger="change from:#source"
     hx-target="#columns-container"
     hx-swap="innerHTML"
     hx-include="#source,#type">
    <p id="columns-list" style="display:none;"><?php echo esc_html(isset($edit_config->columns) ? implode(', ', maybe_unserialize($edit_config->columns)) : ''); ?></p>
    
  </div>
  </td>
</tr>
<tr>
    <th><label for="columns"><?php esc_html_e('Columns', 'smartart-export'); ?></label></th>
      <td>
<textarea name="columns" id="columns" cols="10" rows="3" class="large-text"><?php
    echo esc_textarea(isset($edit_config->columns) ? implode(', ', maybe_unserialize($edit_config->columns)) : '');
?></textarea>
                <p class="description"><?php esc_html_e('Comma-separated list of column names to display', 'smartart-export'); ?></p>
            

  </td>
</tr>
        </table>
        <p class="submit">
            <button type="submit" name="swe_shortcode_submit" class="button button-primary">
    <?php echo $edit_config ? esc_html__('Update Configuration', 'smartart-export') : esc_html__('Save Configuration', 'smartart-export'); ?>
</button>
        </p>
    </form>

    <script>
function copyToClipboard(button) {
    const input = button.previousElementSibling;
    input.select();
    input.setSelectionRange(0, 99999); // for mobile
    document.execCommand("copy");
    button.innerText = "Copied!";
    setTimeout(() => {
        button.innerText = "Copy";
    }, 2000);
}
</script>
<script>
document.addEventListener('htmx:configRequest', (event) => {
  if (
    event.target.matches('#source') || 
    event.target.matches('#type')
  ) {
    const columnsList = document.getElementById('columns-list');
    if (columnsList) {
      columnsList.textContent = '';
    }
  }
});
</script>
<script>

function buildDropdownFromColumns() {
  const columnsListP = document.getElementById('columns-list');
  const textarea = document.getElementById('columns');

  // Remove existing dropdown if present
  let existingDropdown = document.getElementById('columns-list-dropdown');
  if (existingDropdown) {
    existingDropdown.remove();
  }

  if (!columnsListP) return;

  const text = columnsListP.textContent.trim();
  if (!text) return;

  const columns = text.split(',').map(s => s.trim()).filter(Boolean);
  if (columns.length === 0) return;

  // Create dropdown
  const select = document.createElement('select');
  select.id = 'columns-list-dropdown';
  const placeholderOption = document.createElement('option');
  placeholderOption.value = '';
  placeholderOption.textContent = '-- Select column to add --';
  select.appendChild(placeholderOption);

  columns.forEach(col => {
    const option = document.createElement('option');
    option.value = col;
    option.textContent = col;
    select.appendChild(option);
  });

  // Insert dropdown after the columns-list <p>
  columnsListP.insertAdjacentElement('afterend', select);

  // Listen for selection
  select.addEventListener('change', function() {
    const selectedCol = this.value.trim();
    if (!selectedCol) return;

    const currentCols = textarea.value.split(',').map(s => s.trim()).filter(Boolean);
    if (!currentCols.includes(selectedCol)) {
      currentCols.push(selectedCol);
      textarea.value = currentCols.join(', ');
    }

    this.value = '';
  });
}

// Run after HTMX updates columns-list
document.body.addEventListener('htmx:afterSwap', (event) => {
  // Check if the swapped target contains columns-list <p>
  if (event.target.id === 'columns-list') {
    buildDropdownFromColumns();
  }
});

// Also run on page load if columns-list already present
window.addEventListener('DOMContentLoaded', buildDropdownFromColumns);
</script>

</div>
