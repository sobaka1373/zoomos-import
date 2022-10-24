<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin_Admin
{

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/main-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_media();
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/main-admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    public function register_admin_page() {
        add_menu_page(
            'custom plugin', 'Custom plugin title', 'manage_options',
            'custom-plugin-admin', 'admin_page_open', '', 6
        );
//        add_filter( 'option_page_capability_'.'custom-plugin-admin', 'my_page_capability' );
    }

    public function start_import() {
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, "http://icanhazip.com/");
//        curl_setopt($ch, CURLOPT_URL, "https://api.zoomos.by/pricelist?key=vozduh.by-98Q3@D3WN");
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        $output = curl_exec($ch);
//        curl_close($ch);

        $json = file_get_contents("https://api.zoomos.by/pricelist?key=vozduh.by-98Q3@D3WN&limit=1");
        $obj = json_decode($json,true);

        $args = array(
            'post_type' => 'product',
            'meta_key' => 'zoomos_id',
            'meta_value' => $obj[0]['id'],
            'meta_compare' => '='
        );
        $products = wc_get_products($args);

        if (empty($products)) {
            $post_id = wp_insert_post( array(
                'post_title' => $obj[0]['supplierInfo']['model'],
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_content' => '',
            ));
            $product = wc_get_product( $post_id );
            $product->set_price($obj[0]['price']);
            $product->set_regular_price($obj[0]['price']);
            $product->set_stock_quantity($obj[0]['quantity']);
            $imageId = $this->uploadImage($obj[0]['image']);
            $product->set_image_id($imageId);
            $product->update_meta_data('zoomos_id', $obj[0]['id']);
            $product->update_meta_data('zoomos_category', $obj[0]['category']['id']);
            $product->save();
        } else {
            update_post_meta( $products[0]->id, '_regular_price', (float)$obj[0]['price']);
            update_post_meta( $products[0]->id, '_price', (float)$obj[0]['price']);
        }

        wp_die();
    }

    public function uploadImage($imageUrl)
    {
        $image_url = $imageUrl . ".jpeg";

        $upload_dir = wp_upload_dir();

        $image_data = file_get_contents( $image_url );

        $filename = basename( $image_url );

        if ( wp_mkdir_p( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        }
        else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        file_put_contents( $file, $image_data );

        $wp_filetype = wp_check_filetype( $filename, null );

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return $attach_id;
    }

}