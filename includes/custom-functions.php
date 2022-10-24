<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function admin_page_open() {
    ob_start();
    ?>
    <p>Import options</p>
    <input type="submit" class="button button-custom-import" name="insert" value="Import" />
    <p class="hide-message">Import complete</p>
    <?php
    $output = ob_get_clean();
    echo $output;
}