<?php

/**
 * Plugin Name: WP Broken Image Finder And Remover
 * Plugin URI:  # (Replace with your plugin URL)
 * Description: A basic plugin demonstrating a menu page.
 * Version:     1.0
 * Author:      Ashraf Uddin
 * Author URI:  https://wordpressdevservice.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-broken-image-finder-and-remover
 */

// Security check
if (!defined('ABSPATH')) {
    die;
}

// Function to efficiently scan for broken images
function find_and_delete_broken_images() {
  $broken_images = array();

  $query = new WP_Query(array(
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'nopaging' => true, // Get all images
    'fields' => 'ids', // Optimize query by fetching only IDs
  ));

  $image_ids = $query->posts;

  foreach ($image_ids as $image_id) {
    $image_url = wp_get_attachment_url($image_id);
    $response = wp_remote_head($image_url);

    // Check if the image URL exists and returns a 200 status code
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      wp_delete_attachment($image_id, true); // Delete with linked data
      array_push($broken_images, $image_id); // Track deleted images
    }
  }

  return $broken_images;
}

function find_broken_images() {
    $broken_images = array();
    $all_images = get_posts(array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'nopaging' => true, // Get all images
    ));

    foreach ($all_images as $image) {
        $image_url = wp_get_attachment_url($image->ID);
        $response = wp_remote_head($image_url);

        // Check if the image URL exists and returns a 200 status code
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $broken_images[] = array(
                'id' => $image->ID,
                'url' => $image_url,
            );
        }
    }

    return $broken_images;
}

function find_broken_product_images() {
    $broken_images = array();

    // Get all published products
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
    ));

    foreach ($products as $product) {
        // Get product gallery images
        $gallery_ids = $product->get_gallery_image_ids();

        // Add main product image ID to the gallery IDs array
        array_unshift($gallery_ids, $product->get_image_id());

        foreach ($gallery_ids as $image_id) {
            // Get image URL
            $image_url = wp_get_attachment_url($image_id);

            // Check if the image URL is broken
            $response = wp_remote_get($image_url);
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $broken_images[] = array(
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'image_url' => $image_url,
                    'response_code' => $response_code,
                );
            }
        }
    }

    return $broken_images;
}

// Usage example



// Function to create the menu page
function wpbifr_plugin_menu_page() {
    add_menu_page(
        'Broken Image', // Page Title
        'Broken Image', // Menu Label
        'manage_options', // Capability required (e.g., administrator)
        'wpbifr-broken-image', // Menu slug (used for URL)
        'wpbifr_broken_image', // Callback function
        'dashicons-admin-settings', // Menu icon (optional)
        60 // Menu position (optional, higher number = lower position)
    );
}

// Function to display the menu page content
function wpbifr_broken_image() {

    // find all broken image link and remove

    $broken_images_link = find_broken_images();

    if (empty($broken_images_link)) {
        echo 'There are no broken images found.';
    } else {
        echo 'Found ' . count($broken_images_link) . ' broken images:';
        foreach ($broken_images_link as $image) {
            echo '<li> Image ID: ' . $image['id'] . ' - URL: ' . $image['url'] . '</li>';
            // wp_delete_attachment($image['id'], true);
        }
    }
    
    // Find all missing featured image of the products
    $broken_images = find_broken_product_images();
    foreach ($broken_images as $broken_image) {
        echo 'Broken image found in product: ' . $broken_image['product_name'] . '<br>';
        echo 'Image URL: ' . $broken_image['image_url'] . '<br>';
        echo 'Response Code: ' . $broken_image['response_code'] . '<br>';
    }
}

// Hook to add the menu page on admin_menu
add_action('admin_menu', 'wpbifr_plugin_menu_page');
