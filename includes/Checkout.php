<?php
/**
 * Fixed Checkout Handler - Working Version
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSLMS_Trz_Checkout {
    
    protected static $last_invoice = 0;
    
    public static function init() {
        // Ловим создание заказа
        add_action( 'order_created', [ __CLASS__, 'catch_created_order' ], 10, 4 );
        add_filter( 'stm_lms_purchase_done', [ __CLASS__, 'filter_purchase_response' ] );
        
        // Шорткод для страницы оплаты
        add_shortcode( 'mslms_tranzila_pay', [ __CLASS__, 'shortcode_payment_page' ] );
        
        // REST API для IPN
        add_action( 'rest_api_init', function () {
            register_rest_route( 'mslms-tranzila/v1', '/ipn', [
                'methods'  => 'POST,GET',
                'callback' => [ __CLASS__, 'handle_ipn' ],
                'permission_callback' => '__return_true',
            ] );
            
            register_rest_route( 'mslms-tranzila/v1', '/status', [
                'methods'  => 'GET',
                'callback' => [ __CLASS__, 'handle_status' ],
                'permission_callback' => '__return_true',
            ] );
        } );
    }
    
    public static function catch_created_order( $uid, $cart, $payment_code, $invoice ) {
        if ( 'tranzila' === $payment_code ) {
            self::$last_invoice = (int) $invoice;
            update_user_meta( $uid, '_mslms_trz_last_invoice', (int) $invoice );
        }
    }
    
    public static function filter_purchase_response( $response ) {
        $payment_code = isset($_GET['payment_code']) ? sanitize_text_field($_GET['payment_code']) : '';
        
        if ( 'tranzila' !== $payment_code ) {
            return $response;
        }
        
        $invoice = self::$last_invoice;
        if ( ! $invoice ) {
            $uid = get_current_user_id();
            $invoice = (int) get_user_meta( $uid, '_mslms_trz_last_invoice', true );
        }
        
        if ( ! $invoice ) {
            $response['status']  = 'error';
            $response['message'] = 'Order not found';
            return $response;
        }
        
        $order_key = get_post_field( 'post_name', $invoice );
        if ( ! $order_key ) {
            $order_key = 'order_' . $invoice . '_' . time();
            wp_update_post( [
                'ID' => $invoice,
                'post_name' => $order_key
            ] );
        }
        
        $pay_page = (int) get_option( 'mslms_trz_page_id', 0 );
        $pay_link = $pay_page 
            ? add_query_arg( array('order_id'=>$invoice,'key'=>$order_key), get_permalink($pay_page) ) 
            : home_url('/');
        
        $response['url'] = $pay_link;
        $response['message'] = 'Redirecting to payment...';
        $response['status'] = 'success';
        
        return $response;
    }
    
    public static function shortcode_payment_page( $atts ) {
        ob_start();
        
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        
        if ( ! $order_id || get_post_field('post_name', $order_id) !== $key ) {
            echo '<p style="color:red;">Invalid order!</p>';
            return ob_get_clean();
        }
        
        // Получаем данные заказа
        $user_id = (int) get_post_field( 'post_author', $order_id );
        $total = (float) get_post_meta( $order_id, '_order_total', true );
        $user = get_userdata( $user_id );
        
        // Получаем настройки
        $opts = get_option( MSLMS_Trz_Admin::OPT_KEY, [] );
        $terminal = isset($opts['terminal']) ? trim($opts['terminal']) : '';
        
        if ( ! $terminal ) {
            echo '<p style="color:red;">Terminal not configured!</p>';
            return ob_get_clean();
        }
        
        // IPN URL только
        $notify_url = rest_url( 'mslms-tranzila/v1/ipn' );
        
        // Базовые параметры iframe БЕЗ success/fail URLs
        $iframe_params = [
            'sum' => number_format($total, 2, '.', ''),
            'currency' => isset($opts['currency']) ? $opts['currency'] : '1',
            'cred_type' => isset($opts['cred_type']) ? $opts['cred_type'] : '1',
            'lang' => isset($opts['language']) ? $opts['language'] : 'il',
            'tranmode' => 'A',
            'orderid' => $order_id,
            'email' => $user ? $user->user_email : '',
            'contact' => $user ? $user->display_name : '',
            'notify_url_address' => $notify_url,
        ];
        
        // Добавляем максимальное количество платежей если включены рассрочки
        if ( isset($opts['cred_type']) && $opts['cred_type'] == '8' && !empty($opts['maxpay']) ) {
            $iframe_params['maxpay'] = $opts['maxpay'];
        }
        
        // Настройки цветов
        if ( !empty($opts['trBgColor']) ) {
            $iframe_params['trBgColor'] = str_replace('#', '', $opts['trBgColor']);
        }
        if ( !empty($opts['trTextColor']) ) {
            $iframe_params['trTextColor'] = str_replace('#', '', $opts['trTextColor']);
        }
        if ( !empty($opts['trButtonColor']) ) {
            $iframe_params['trButtonColor'] = str_replace('#', '', $opts['trButtonColor']);
            $iframe_params['buttonBgColor'] = str_replace('#', '', $opts['trButtonColor']);
        }
        if ( !empty($opts['buttonTextColor']) ) {
            $iframe_params['buttonTextColor'] = str_replace('#', '', $opts['buttonTextColor']);
        }
        
        // Включаем методы оплаты
        $payment_methods = [];
        if ( !empty($opts['enable_bit']) ) $payment_methods[] = 'bit';
        if ( !empty($opts['enable_applepay']) ) $payment_methods[] = 'applepay';
        if ( !empty($opts['enable_googlepay']) ) $payment_methods[] = 'googlepay';
        if ( !empty($opts['enable_paypal']) ) $payment_methods[] = 'paypal';
        
        if ( !empty($payment_methods) ) {
            $iframe_params['payment_methods'] = implode(',', $payment_methods);
        }
        
        // Строим URL
        $iframe_url = 'https://direct.tranzila.com/' . $terminal . '/iframenew.php?' . http_build_query($iframe_params);
        
        // Настройки отображения
        $hide_response = !empty($opts['hide_iframe_response']);
        $loading_animation = isset($opts['loading_animation']) ? $opts['loading_animation'] : 'spinner';
        
        // Символы валют
        $currency_symbols = ['1' => '₪', '2' => '$', '978' => '€', '826' => '£', '392' => '¥'];
        $currency_code = isset($opts['currency']) ? $opts['currency'] : '1';
        $symbol = isset($currency_symbols[$currency_code]) ? $currency_symbols[$currency_code] : '';
        
        ?>
        <style>
            .trz-payment-wrap {
                max-width: 900px;
                margin: 20px auto;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 10px;
            }
            .trz-info {
                background: white;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .trz-info-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .trz-info-row:last-child {
                border-bottom: none;
            }
            
            /* Payment methods icons */
            .trz-payment-methods {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin: 15px 0;
                padding: 15px;
                background: white;
                border-radius: 5px;
            }
            .trz-payment-method {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                background: #f0f0f0;
                border-radius: 5px;
                font-size: 14px;
            }
            
            /* Iframe container */
            .trz-iframe-container {
                background: white;
                padding: 10px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                position: relative;
                overflow: hidden;
            }
            .trz-iframe {
                width: 100%;
                min-height: 800px;
                max-height: 1200px;
                height: 900px;
                border: none;
                transition: opacity 0.3s;
            }
            .trz-iframe.processing {
                opacity: 0.3;
                pointer-events: none;
            }
            
            /* Overlay */
            .trz-iframe-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.95);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10;
                flex-direction: column;
            }
            .trz-iframe-overlay.active {
                display: flex;
            }
            
            /* Loading animations */
            .trz-loading-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .trz-status {
                text-align: center;
                padding: 15px;
                margin-top: 20px;
                background: #e7f3ff;
                border-radius: 5px;
                transition: all 0.3s;
            }
            .trz-status.success {
                background: #d4edda;
                color: #155724;
            }
            .trz-status.error {
                background: #f8d7da;
                color: #721c24;
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .trz-payment-wrap {
                    margin: 0;
                    padding: 10px 0;
                    border-radius: 0;
                    background: transparent;
                }
                
                .trz-iframe-container {
                    margin: 0;
                    padding: 0;
                    border-radius: 0;
                    box-shadow: none;
                }
                
                .trz-iframe {
                    min-height: 800px;
                    height: 100vh;
                    max-height: 1000px;
                }
            }
        </style>
        
        <div class="trz-payment-wrap">
            <div class="trz-info">
                <h2><?php esc_html_e('Payment Details', 'mslms-tranzila'); ?></h2>
                <div class="trz-info-row">
                    <span><strong><?php esc_html_e('Order #', 'mslms-tranzila'); ?>:</strong></span>
                    <span><?php echo $order_id; ?></span>
                </div>
                <div class="trz-info-row">
                    <span><strong><?php esc_html_e('Amount', 'mslms-tranzila'); ?>:</strong></span>
                    <span style="font-size: 1.2em; color: #28a745;">
                        <?php echo $symbol . ' ' . number_format($total, 2); ?>
                    </span>
                </div>
                <div class="trz-info-row">
                    <span><strong><?php esc_html_e('Email', 'mslms-tranzila'); ?>:</strong></span>
                    <span><?php echo $user ? esc_html($user->user_email) : 'Guest'; ?></span>
                </div>
            </div>
            
            <?php if ( !empty($payment_methods) || !isset($opts['enable_creditcard']) || !empty($opts['enable_creditcard']) ) : ?>
            <div class="trz-payment-methods">
                <div class="trz-payment-method">
                    <span>💳 Cards</span>
                </div>
                <?php if ( !empty($opts['enable_bit']) ) : ?>
                <div class="trz-payment-method">
                    <span style="font-weight: bold; color: #00d4aa;">bit</span>
                </div>
                <?php endif; ?>
                <?php if ( !empty($opts['enable_applepay']) ) : ?>
                <div class="trz-payment-method">
                    <span>🍎 Apple Pay</span>
                </div>
                <?php endif; ?>
                <?php if ( !empty($opts['enable_googlepay']) ) : ?>
                <div class="trz-payment-method">
                    <span>Google Pay</span>
                </div>
                <?php endif; ?>
                <?php if ( !empty($opts['enable_paypal']) ) : ?>
                <div class="trz-payment-method">
                    <span style="color: #0070ba;">PayPal</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="trz-iframe-container">
                <iframe 
                    id="tranzila_iframe" 
                    class="trz-iframe" 
                    src="<?php echo esc_url($iframe_url); ?>"
                    allow="payment">
                </iframe>
                
                <!-- Overlay для скрытия после оплаты -->
                <div class="trz-iframe-overlay" id="iframe_overlay">
                    <div class="trz-loading-spinner"></div>
                    <p style="margin-top: 20px; font-size: 18px;">
                        <?php esc_html_e('Processing payment...', 'mslms-tranzila'); ?>
                    </p>
                </div>
            </div>
            
            <div class="trz-status" id="status_box">
                ⏳ <?php esc_html_e('Waiting for payment...', 'mslms-tranzila'); ?>
            </div>
        </div>
        
        <script>
        (function() {
            var orderId = <?php echo $order_id; ?>;
            var orderKey = '<?php echo esc_js($key); ?>';
            var checkCount = 0;
            var hideResponse = <?php echo $hide_response ? 'true' : 'false'; ?>;
            var isProcessing = false;
            var paymentCompleted = false;
            
            var iframe = document.getElementById('tranzila_iframe');
            var overlay = document.getElementById('iframe_overlay');
            var statusBox = document.getElementById('status_box');
            
            function checkStatus() {
                if (checkCount > 150 || paymentCompleted) return;
                checkCount++;
                
                fetch('<?php echo rest_url('mslms-tranzila/v1/status'); ?>?order_id=' + orderId + '&key=' + orderKey)
                    .then(r => r.json())
                    .then(function(data) {
                        if (data.status === 'completed' && !paymentCompleted) {
                            paymentCompleted = true;
                            isProcessing = true;
                            
                            // Показываем overlay
                            iframe.classList.add('processing');
                            overlay.classList.add('active');
                            
                            statusBox.className = 'trz-status success';
                            statusBox.innerHTML = '✅ <?php echo esc_js(__('Payment completed! Redirecting...', 'mslms-tranzila')); ?>';
                            
                            // Редирект на страницу успеха
                            setTimeout(function() {
                                var successUrl = '<?php 
                                    $checkout_url = function_exists('STM_LMS_Options') ? STM_LMS_Options::get_option('checkout_url') : '';
                                    $checkout_link = $checkout_url ? get_permalink((int)$checkout_url) : home_url('/');
                                    $final_url = $checkout_link ? trailingslashit($checkout_link) . 'masterstudy-orders-received/' . $order_id . '/?key=' . $key : home_url('/');
                                    echo esc_js($final_url);
                                ?>';
                                
                                // Используем top для выхода из всех фреймов
                                if (window.top !== window.self) {
                                    window.top.location.href = successUrl;
                                } else {
                                    window.location.href = successUrl;
                                }
                            }, 2000);
                            
                        } else if (data.status === 'failed') {
                            statusBox.className = 'trz-status error';
                            statusBox.innerHTML = '❌ <?php echo esc_js(__('Payment failed. Please try again.', 'mslms-tranzila')); ?>';
                            
                            // Перезагрузим страницу через 3 секунды чтобы попробовать снова
                            setTimeout(function() {
                                window.location.reload();
                            }, 3000);
                            
                        } else if (!isProcessing) {
                            // Продолжаем проверку
                            setTimeout(checkStatus, 2000);
                        }
                    })
                    .catch(function(error) {
                        console.log('Status check error:', error);
                        if (!isProcessing && !paymentCompleted) {
                            setTimeout(checkStatus, 3000);
                        }
                    });
            }
            
            // Начинаем проверку через 5 секунд после загрузки
            setTimeout(checkStatus, 5000);
            
            // Дополнительная проверка каждые 30 секунд
            setInterval(function() {
                if (!paymentCompleted && !isProcessing) {
                    checkStatus();
                }
            }, 30000);
            
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    // IPN обработчик
    public static function handle_ipn( $request ) {
        $params = $request->get_params();
        
        // Логируем если включен debug
        $opts = get_option( MSLMS_Trz_Admin::OPT_KEY, [] );
        if ( !empty($opts['debug_mode']) ) {
            error_log('[Tranzila IPN] ' . json_encode($params));
        }
        
        // Получаем order_id
        $order_id = 0;
        foreach ( ['orderid', 'order_id', 'myid'] as $field ) {
            if ( isset($params[$field]) ) {
                $order_id = absint($params[$field]);
                break;
            }
        }
        
        if ( ! $order_id ) {
            return new WP_REST_Response(['ok'=>false,'reason'=>'no_order_id'], 400);
        }
        
        // Получаем данные пользователя
        $user_id = (int) get_post_field('post_author', $order_id);
        $user = get_userdata($user_id);
        
        // Проверяем успешность
        $success = false;
        $response_code = '';
        
        if ( isset($params['Response']) ) {
            $response_code = $params['Response'];
            $success = ($response_code === '000');
        } elseif ( isset($params['response']) ) {
            $response_code = $params['response'];
            $success = ($response_code === '000' || $response_code === '0');
        }
        
        // Получаем transaction ID
        $transaction_id = '';
        foreach ( ['Tempref', 'transaction_id', 'txId', 'ConfirmationCode', 'TranzilaTK'] as $field ) {
            if ( !empty($params[$field]) ) {
                $transaction_id = $params[$field];
                break;
            }
        }
        
        // Получаем сумму
        $amount = 0;
        if ( isset($params['sum']) ) {
            $amount = floatval($params['sum']);
        } elseif ( isset($params['amount']) ) {
            $amount = floatval($params['amount']);
        } else {
            $amount = (float) get_post_meta( $order_id, '_order_total', true );
        }
        
        // Получаем валюту
        $currency = isset($params['currency']) ? $params['currency'] : '1';
        
        // Сохраняем в базу данных
        global $wpdb;
        $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
        
        // Проверяем, есть ли уже запись для этого заказа
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        $transaction_data = [
            'order_id' => $order_id,
            'transaction_id' => $transaction_id,
            'status' => $success ? 'completed' : 'failed',
            'response_code' => $response_code,
            'amount' => $amount,
            'currency' => $currency,
            'customer_name' => $user ? $user->display_name : '',
            'customer_email' => $user ? $user->user_email : (isset($params['email']) ? $params['email'] : ''),
            'response_data' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ];
        
        if ( $existing ) {
            // Обновляем существующую запись
            $wpdb->update(
                $table_name,
                $transaction_data,
                ['id' => $existing]
            );
        } else {
            // Создаем новую запись
            $transaction_data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $transaction_data);
        }
        
        // Сохраняем данные в мета заказа
        update_post_meta( $order_id, '_tranzila_response', $params );
        update_post_meta( $order_id, '_tranzila_transaction_id', $transaction_id );
        update_post_meta( $order_id, '_tranzila_response_code', $response_code );
        
        if ( $success ) {
            update_post_meta( $order_id, 'status', 'completed' );
            
            // Активируем заказ в MasterStudy
            if ( class_exists('STM_LMS_Order') ) {
                STM_LMS_Order::accept_order( $user_id, $order_id );
            }
            
            // Устанавливаем флаг что оплата прошла
            set_transient( 'mslms_trz_done_' . $order_id, 1, 600 );
        } else {
            update_post_meta( $order_id, 'status', 'failed' );
        }
        
        return new WP_REST_Response(['ok'=>true,'status'=>$success?'completed':'failed'], 200);
    }
    
    // Проверка статуса
    public static function handle_status( $request ) {
        $order_id = absint( $request->get_param('order_id') );
        $key = sanitize_text_field( $request->get_param('key') );
        
        if ( ! $order_id || get_post_field('post_name', $order_id) !== $key ) {
            return new WP_REST_Response(['status'=>'invalid'], 200);
        }
        
        // Проверяем транзиент
        if ( get_transient( 'mslms_trz_done_' . $order_id ) ) {
            return new WP_REST_Response(['status'=>'completed'], 200);
        }
        
        // Проверяем статус в мета
        $status = get_post_meta( $order_id, 'status', true );
        
        return new WP_REST_Response(['status'=> $status ?: 'pending'], 200);
    }
}