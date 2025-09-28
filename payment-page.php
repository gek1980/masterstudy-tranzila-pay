<?php 
/**
 * Payment Page Template for Tranzila iframe
 * 
 * Available variables:
 * $iframe_url - Full URL for Tranzila iframe
 * $order_id - Order ID
 * $key - Order security key
 * $order_total - Order amount
 * $currency_symbol - Currency symbol
 * $user_name - Customer name
 * $user_email - Customer email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<style>
    .mslms-trz-container {
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
    }
    
    .mslms-trz-iframe-wrapper {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        margin: 20px 0;
    }
    
    .mslms-trz-iframe {
        width: 100%;
        height: 700px;
        border: none;
    }
    
    .mslms-trz-status {
        text-align: center;
        padding: 15px;
        margin: 20px 0;
        background: #f0f0f0;
        border-radius: 5px;
    }
    
    .mslms-trz-info {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .mslms-trz-info h3 {
        margin-top: 0;
        color: #333;
    }
    
    .mslms-trz-info p {
        margin: 5px 0;
        color: #666;
    }
    
    .mslms-trz-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(0,0,0,.3);
        border-radius: 50%;
        border-top-color: #000;
        animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="mslms-trz-container">
    <div class="mslms-trz-info">
        <h3><?php esc_html_e( 'Payment Information', 'mslms-tranzila' ); ?></h3>
        <p><strong><?php esc_html_e( 'Order #', 'mslms-tranzila' ); ?>:</strong> <?php echo esc_html( $order_id ); ?></p>
        <p><strong><?php esc_html_e( 'Amount', 'mslms-tranzila' ); ?>:</strong> <?php echo esc_html( number_format($order_total, 2) . ' ' . $currency_symbol ); ?></p>
        <p><strong><?php esc_html_e( 'Customer', 'mslms-tranzila' ); ?>:</strong> <?php echo esc_html( $user_email ); ?></p>
    </div>
    
    <div class="mslms-trz-iframe-wrapper">
        <iframe 
            id="tranzila_frame" 
            class="mslms-trz-iframe" 
            src="<?php echo esc_url( $iframe_url ); ?>" 
            allow="payment">
        </iframe>
    </div>
    
    <div class="mslms-trz-status" id="payment_status">
        <span class="mslms-trz-spinner"></span>
        <?php esc_html_e( 'Waiting for payment confirmation...', 'mslms-tranzila' ); ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var orderId = '<?php echo esc_js( $order_id ); ?>';
    var orderKey = '<?php echo esc_js( $key ); ?>';
    var checkCount = 0;
    var maxChecks = 150; // 5 minutes
    
    function checkPaymentStatus() {
        if (checkCount >= maxChecks) {
            return;
        }
        
        checkCount++;
        
        $.ajax({
            url: '<?php echo esc_url( rest_url('mslms-tranzila/v1/status') ); ?>',
            method: 'GET',
            data: {
                order_id: orderId,
                key: orderKey
            },
            success: function(response) {
                if (response.ok && response.status === 'completed') {
                    $('#payment_status').html('<span style="color: green;">✓ <?php esc_html_e( 'Payment completed! Redirecting...', 'mslms-tranzila' ); ?></span>');
                    
                    // Redirect to success page
                    setTimeout(function() {
                        <?php
                        // Build success URL properly
                        $checkout_url = '';
                        if ( function_exists('STM_LMS_Options') ) {
                            $checkout_id = STM_LMS_Options::get_option( 'checkout_url' );
                            if ( $checkout_id ) {
                                $checkout_url = get_permalink( (int)$checkout_id );
                            }
                        }
                        
                        if ( $checkout_url ) {
                            $success_url = trailingslashit( $checkout_url ) . 'masterstudy-orders-received/' . $order_id . '/?key=' . $key;
                        } else {
                            $course_id = (int) get_post_meta( $order_id, 'item_id', true );
                            $success_url = $course_id ? get_permalink( $course_id ) : home_url('/');
                        }
                        ?>
                        window.location.href = '<?php echo esc_js( $success_url ); ?>';
                    }, 1500);
                    
                } else if (response.status === 'failed') {
                    $('#payment_status').html('<span style="color: red;">✗ <?php esc_html_e( 'Payment failed. Please try again.', 'mslms-tranzila' ); ?></span>');
                } else {
                    // Continue checking
                    setTimeout(checkPaymentStatus, 2000);
                }
            },
            error: function() {
                // Continue checking on error
                setTimeout(checkPaymentStatus, 3000);
            }
        });
    }
    
    // Start checking after 3 seconds
    setTimeout(checkPaymentStatus, 3000);
    
    // Also try to detect iframe navigation (won't work cross-origin but worth trying)
    var iframe = document.getElementById('tranzila_frame');
    if (iframe) {
        iframe.onload = function() {
            try {
                var iframeUrl = iframe.contentWindow.location.href;
                if (iframeUrl.indexOf('tranzila-return') !== -1) {
                    window.location.href = iframeUrl;
                }
            } catch(e) {
                // Cross-origin - expected
            }
        };
    }
});
</script>