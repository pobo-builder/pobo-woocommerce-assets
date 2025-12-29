<?php
/**
 * Uninstall script for Pobo WooCommerce Assets
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data from the database.
 *
 * @package Pobo_WooCommerce_Assets
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'pobo_woocommerce_assets_settings' );

// For multisite, delete options from all sites
if ( is_multisite() ) {
    $sites = get_sites();
    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );
        delete_option( 'pobo_woocommerce_assets_settings' );
        restore_current_blog();
    }
}
