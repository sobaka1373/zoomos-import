<?php

namespace entity;

class Product
{
    protected string $name;
    protected int $zoomos_id;
    protected int $zoomos_category;
    protected int $sku;
    protected float $price;
    protected int $main_image;
    protected string $status;
    protected int $quantity;
    protected array $gallery;
    protected array $attributes;
    protected string $description;
    protected string $short_description;

    public function __construct( $name, $zoomos_id, $zoomos_category )
    {
        $this->name = $name;
        $this->zoomos_id = $zoomos_id;
        $this->zoomos_category = $zoomos_category;
        $this->sku = $zoomos_id;
    }

    public function getProductName(): string
    {
        return $this->name;
    }

    public function setProductName(string $name): void
    {
        $this->name = $name;
    }

    public function getProductZoomosId(): int
    {
        return $this->zoomos_id;
    }

    public function setProductZoomosId(int $id): void
    {
        $this->zoomos_id = $id;
    }

    public function getProductZoomosCategory(): string
    {
        return $this->zoomos_category;
    }

    public function setProductZoomosCategory(int $category): void
    {
        $this->zoomos_category = $category;
    }

    public function getProductSku(): int
    {
        return $this->sku;
    }

    public function setProductSku(int $sku): void
    {
        $this->sku = $sku;
    }

    public function getProductMainImage(): int
    {
        return $this->main_image;
    }

    public function setProductMainImage(string $imageLink, string $productName): void
    {
        $image_url = $imageLink . ".jpeg";
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $result = $productName;
        $filename = $result . basename($image_url);
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
        $this->main_image = $attach_id;
    }

    public function getProductStatus(): string
    {
        return $this->status;
    }

    public function setProductStatus($status): void
    {
        $this->status = $status;
    }

    public function getProductQuantity(): int
    {
        return $this->quantity;
    }

    public function setProductQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getProductGallery(): array
    {
        return $this->gallery;
    }

    public function setProductGallery(array $gallery): void
    {
        $this->gallery = $gallery;
    }

    public function getProductAttributes(): array
    {
        return $this->attributes;
    }

    public function setProductAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getProductPrice(): float
    {
        return $this->price;
    }

    public function setProductPrice(float $price): void
    {
        $this->price = $price;
    }
}