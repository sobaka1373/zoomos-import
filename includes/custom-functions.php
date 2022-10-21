<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function admin_page_open() {
    ob_start();
    ?>
    <p>Test</p>
    <?php
    $output = ob_get_clean();
    echo $output;
}