<?php
/**
 * Plugin Name: Admin Order Navigation for WooCommerce
 * Plugin URI: https://www.sprucely.net/
 * Description: Adds Next and Previous navigation buttons to WooCommerce order edit screen, compatible with HPOS.
 * Version: 1.1
 * Author: Isaac Russell @ Sprucely Designed
 * Author URI: https://www.sprucely.net
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

// Main plugin class
class Sprucely_WC_Order_Navigation_HPOS_Compatible {

    // Constructor
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_navigation_meta_box' ) );
    }

    // Add meta box with dynamic screen ID based on HPOS
    public function add_navigation_meta_box() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'order_navigation',
            __( 'Order Navigation', 'woocommerce' ),
            array( $this, 'order_navigation_meta_box_content' ),
            $screen,
            'side',
            'high'
        );
    }

    // Meta box content
    public function order_navigation_meta_box_content( $post ) {
        // Get next and previous order IDs
        $prev_order_id = $this->get_adjacent_order_id( $post->ID, 'prev' );
        $next_order_id = $this->get_adjacent_order_id( $post->ID, 'next' );

        echo '<div>';
        if ( $prev_order_id ) {
            $prev_order_edit_link = get_edit_post_link( $prev_order_id );
            echo '<a href="' . esc_url( $prev_order_edit_link ) . '" class="button">' . esc_html__( 'Previous Order', 'woocommerce' ) . '</a>';
        }
        if ( $next_order_id ) {
            $next_order_edit_link = get_edit_post_link( $next_order_id );
            echo '<a href="' . esc_url( $next_order_edit_link ) . '" class="button">' . esc_html__( 'Next Order', 'woocommerce' ) . '</a>';
        }
        echo '</div>';
    }

    // Get adjacent order ID
    private function get_adjacent_order_id( $order_id, $direction = 'next' ) {
        // Use WC API to determine if HPOS is enabled
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $orders = wc_get_orders( array(
                'limit'        => 1,
                'orderby'      => 'id',
                'order'        => $direction === 'prev' ? 'DESC' : 'ASC',
                'return'       => 'ids',
                'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
                'date_created' => $direction === 'prev' ? '<' : '>',
            ) );

            return !empty($orders) ? $orders[0] : null;
        } else {
            // Fallback for traditional storage
            global $wpdb;
            $operator = ( 'prev' === $direction ) ? '<' : '>';
            $order = ( 'prev' === $direction ) ? 'DESC' : 'ASC';
            $query = $wpdb->prepare( "
                SELECT posts.ID
                FROM $wpdb->posts AS posts
                WHERE posts.post_type = 'shop_order'
                AND posts.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold' )
                AND posts.ID $operator %d
                ORDER BY posts.ID $order
                LIMIT 1
            ", $order_id );
            return $wpdb->get_var( $query );
        }
    }
}

// Initialize the plugin
new Sprucely_WC_Order_Navigation_HPOS_Compatible();
