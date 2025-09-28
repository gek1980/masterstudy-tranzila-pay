<?php
/**
 * Plugin Name: MasterStudy LMS - Tranzila Gateway
 * Plugin URI: https://your-site.com/
 * Description: Tranzila payment gateway integration for MasterStudy LMS
 * Version: 7.0
 * Author: Your Name
 * Author URI: https://your-site.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mslms-tranzila
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MSLMS_TRZ_VER', '7.0' );
define( 'MSLMS_TRZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSLMS_TRZ_URL', plugin_dir_url( __FILE__ ) );
define( 'MSLMS_TRZ_FILE', __FILE__ );

// Load required files
require_once MSLMS_TRZ_DIR . 'includes/Admin.php';
require_once MSLMS_TRZ_DIR . 'includes/Checkout.php';

// Activation hook
register_activation_hook( __FILE__, 'mslms_tranzila_activate' );
function mslms_tranzila_activate() {
    // Create payment page if it doesn't exist
    $page_id = (int) get_option( 'mslms_trz_page_id', 0 );
    
    if ( ! $page_id || 'trash' === get_post_status( $page_id ) ) {
        $page_id = wp_insert_post( array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Tranzila Payment',
            'post_content' => '[mslms_tranzila_pay]',
            'post_name'    => 'tranzila-payment',
        ) );
        
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'mslms_trz_page_id', $page_id );
        }
    }
    
    // Create database table for transactions
    global $wpdb;
    $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_id varchar(100),
        status varchar(50),
        response_code varchar(10),
        amount decimal(10,2),
        currency varchar(10),
        customer_name varchar(255),
        customer_email varchar(255),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        response_data longtext,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY transaction_id (transaction_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    // Add version for future updates
    update_option( 'mslms_trz_db_version', '1.0' );
    
    // Sync settings with MasterStudy
    if ( class_exists( 'MSLMS_Trz_Admin' ) ) {
        MSLMS_Trz_Admin::sync_into_ms_settings();
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'mslms_tranzila_deactivate' );
function mslms_tranzila_deactivate() {
    flush_rewrite_rules();
}

// Initialize plugin
add_action( 'plugins_loaded', 'mslms_tranzila_init', 5 );
function mslms_tranzila_init() {
    // Check if MasterStudy LMS is active
    if ( ! class_exists( 'STM_LMS_Cart' ) ) {
        add_action( 'admin_notices', 'mslms_tranzila_admin_notice' );
        return;
    }
    
    // Initialize components
    MSLMS_Trz_Admin::init();
    MSLMS_Trz_Checkout::init();
}

// Admin notice if MasterStudy LMS is not active
function mslms_tranzila_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e( 'MasterStudy LMS must be active for the Tranzila gateway to work.', 'mslms-tranzila' ); ?></p>
    </div>
    <?php
}

// Handle AJAX status check
add_action( 'wp_ajax_mslms_trz_check_status', 'mslms_trz_ajax_check_status' );
add_action( 'wp_ajax_nopriv_mslms_trz_check_status', 'mslms_trz_ajax_check_status' );

function mslms_trz_ajax_check_status() {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
    
    if ( ! $order_id || get_post_field('post_name', $order_id) !== $key ) {
        wp_send_json_error( array( 'message' => 'Invalid order' ) );
    }
    
    $status = get_post_meta( $order_id, 'status', true );
    $is_completed = ( 'completed' === $status );
    
    wp_send_json_success( array(
        'status' => $status ?: 'pending',
        'completed' => $is_completed
    ) );
}