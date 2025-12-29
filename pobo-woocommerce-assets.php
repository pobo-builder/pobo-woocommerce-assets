<?php
/**
 * Plugin Name: Pobo WooCommerce Assets
 * Plugin URI: https://github.com/pobo-builder/pobo-woocommerce-assets
 * Description: Adds Pobo Page Builder SDK script to your WooCommerce store.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Pobo
 * Author URI: https://pobo.cz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pobo-woocommerce-assets
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 9.4
 * Requires Plugins: woocommerce
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class Pobo_WooCommerce_Assets {

    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Option name for plugin settings
     */
    const OPTION_NAME = 'pobo_woocommerce_assets_settings';

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_head', array( $this, 'output_script' ), 1 );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
        add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Show admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        if ( ! $this->is_woocommerce_active() ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        /* translators: %s: WooCommerce plugin name */
                        esc_html__( '%s requires WooCommerce to be installed and active.', 'pobo-woocommerce-assets' ),
                        '<strong>Pobo WooCommerce Assets</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Pobo WooCommerce Assets', 'pobo-woocommerce-assets' ),
            __( 'Pobo Assets', 'pobo-woocommerce-assets' ),
            'manage_options',
            'pobo-woocommerce-assets',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Add settings link to plugin actions
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=pobo-woocommerce-assets' ) ) . '">' . __( 'Settings', 'pobo-woocommerce-assets' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'pobo_woocommerce_assets',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => $this->get_default_settings(),
            )
        );

        add_settings_section(
            'pobo_woocommerce_assets_main',
            __( 'SDK Settings', 'pobo-woocommerce-assets' ),
            array( $this, 'render_section_description' ),
            'pobo-woocommerce-assets'
        );

        add_settings_field(
            'enabled',
            __( 'Enable', 'pobo-woocommerce-assets' ),
            array( $this, 'render_enabled_field' ),
            'pobo-woocommerce-assets',
            'pobo_woocommerce_assets_main'
        );

        add_settings_field(
            'eshop_id',
            __( 'Eshop ID', 'pobo-woocommerce-assets' ),
            array( $this, 'render_eshop_id_field' ),
            'pobo-woocommerce-assets',
            'pobo_woocommerce_assets_main'
        );

        add_settings_field(
            'eshop_url',
            __( 'Eshop URL', 'pobo-woocommerce-assets' ),
            array( $this, 'render_eshop_url_field' ),
            'pobo-woocommerce-assets',
            'pobo_woocommerce_assets_main'
        );
    }

    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            'enabled'   => false,
            'eshop_id'  => '',
            'eshop_url' => get_site_url(),
        );
    }

    /**
     * Get plugin settings
     */
    private function get_settings() {
        $defaults = $this->get_default_settings();
        $settings = get_option( self::OPTION_NAME, $defaults );
        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['enabled'] = ! empty( $input['enabled'] );
        $sanitized['eshop_id'] = sanitize_text_field( $input['eshop_id'] ?? '' );
        $sanitized['eshop_url'] = esc_url_raw( $input['eshop_url'] ?? get_site_url() );

        return $sanitized;
    }

    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__( 'Configure the Pobo Page Builder SDK settings for your WooCommerce store.', 'pobo-woocommerce-assets' ) . '</p>';
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], true ); ?>>
            <?php esc_html_e( 'Enable Pobo SDK script', 'pobo-woocommerce-assets' ); ?>
        </label>
        <?php
    }

    /**
     * Render eshop ID field
     */
    public function render_eshop_id_field() {
        $settings = $this->get_settings();
        ?>
        <input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[eshop_id]" value="<?php echo esc_attr( $settings['eshop_id'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 22249', 'pobo-woocommerce-assets' ); ?>">
        <p class="description"><?php esc_html_e( 'Your Pobo Eshop ID. You can find this in your Pobo dashboard.', 'pobo-woocommerce-assets' ); ?></p>
        <?php
    }

    /**
     * Render eshop URL field
     */
    public function render_eshop_url_field() {
        $settings = $this->get_settings();
        ?>
        <input type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[eshop_url]" value="<?php echo esc_attr( $settings['eshop_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_site_url() ); ?>">
        <p class="description"><?php esc_html_e( 'Your store URL. Defaults to your WordPress site URL.', 'pobo-woocommerce-assets' ); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'pobo_woocommerce_assets' );
                do_settings_sections( 'pobo-woocommerce-assets' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Output the SDK script in the head
     */
    public function output_script() {
        // Don't output in admin
        if ( is_admin() ) {
            return;
        }

        // Check if WooCommerce is active
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        $settings = $this->get_settings();

        // Check if enabled and has eshop ID
        if ( empty( $settings['enabled'] ) || empty( $settings['eshop_id'] ) ) {
            return;
        }

        $eshop_url = ! empty( $settings['eshop_url'] ) ? $settings['eshop_url'] : get_site_url();
        ?>
        <script
            type="text/javascript"
            async
            id="pobo-b2b-sdk"
            data-pobo-eshop-url="<?php echo esc_url( $eshop_url ); ?>"
            data-pobo-eshop-id="<?php echo esc_attr( $settings['eshop_id'] ); ?>"
            fetchpriority="high"
            src="https://image.pobo.space/assets/b2b.js">
        </script>
        <?php
    }
}

// Initialize the plugin
Pobo_WooCommerce_Assets::get_instance();
