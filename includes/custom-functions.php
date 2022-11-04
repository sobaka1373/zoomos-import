<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function admin_page_open() {
    ob_start();
    ?>
    <div class="container">
      <p>Import options</p>
      <label>
        API key
        <input type="text" id="api-key-input" class="api-key-input" name="api-key" value="<?php echo get_option('zoomos_api_key'); ?>" />
      </label>
      <label>
        Limit
        <input type="text" id="product_limit" class="api-key-input" name="api-limit" value="1" />
      </label>
      <input type="submit" class="button button-custom-import" name="insert" value="Import" />
      <p class="hide-message">Import complete</p>
      <span class="spinner"></span>
    </div>
    <?php
    $output = ob_get_clean();
    echo $output;
}