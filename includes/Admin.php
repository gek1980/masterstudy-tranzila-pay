<?php
/**
 * Admin Settings with Transactions Page
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

class MSLMS_Trz_Admin {
    
    const OPT_KEY = 'mslms_tranzila_settings';
    
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_filter( 'stm_lms_payment_methods', [ __CLASS__, 'filter_payment_method_labels' ] );
        add_action( 'admin_init', [ __CLASS__, 'sync_into_ms_settings' ] );
        add_action( 'updated_option', function( $option ) {
            if ( $option === self::OPT_KEY ) {
                self::sync_into_ms_settings();
            }
        } );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
        
        // AJAX handlers for transactions
        add_action( 'wp_ajax_trz_get_transaction_details', [ __CLASS__, 'ajax_get_transaction_details' ] );
        add_action( 'wp_ajax_trz_export_transactions', [ __CLASS__, 'ajax_export_transactions' ] );
    }
    
    public static function menu() {
        // Main menu
        add_menu_page(
            __( 'Tranzila Gateway', 'mslms-tranzila' ),
            __( 'Tranzila', 'mslms-tranzila' ),
            'manage_options',
            'mslms-tranzila',
            [ __CLASS__, 'settings_page' ],
            'dashicons-cart',
            85
        );
        
        // Rename first submenu
        add_submenu_page(
            'mslms-tranzila',
            __( 'Settings', 'mslms-tranzila' ),
            __( 'Settings', 'mslms-tranzila' ),
            'manage_options',
            'mslms-tranzila',
            [ __CLASS__, 'settings_page' ]
        );
        
        // Transactions submenu
        add_submenu_page(
            'mslms-tranzila',
            __( 'Transactions', 'mslms-tranzila' ),
            __( 'Transactions', 'mslms-tranzila' ),
            'manage_options',
            'mslms-tranzila-transactions',
            [ __CLASS__, 'transactions_page' ]
        );
        
        // Statistics submenu
        add_submenu_page(
            'mslms-tranzila',
            __( 'Statistics', 'mslms-tranzila' ),
            __( 'Statistics', 'mslms-tranzila' ),
            'manage_options',
            'mslms-tranzila-statistics',
            [ __CLASS__, 'statistics_page' ]
        );
    }
    
    public static function register_settings() {
        register_setting( 'mslms_trz_group', self::OPT_KEY );
        
        // Basic Settings Section
        add_settings_section( 
            'mslms_trz_main', 
            __( 'Basic Settings', 'mslms-tranzila' ), 
            function() {
                echo '<p>' . __( 'Configure your Tranzila gateway settings', 'mslms-tranzila' ) . '</p>';
            },
            'mslms_trz' 
        );
        
        // Payment Methods Section
        add_settings_section( 
            'mslms_trz_methods', 
            __( 'Payment Methods', 'mslms-tranzila' ), 
            function() {
                echo '<p>' . __( 'Enable payment methods available in your Tranzila account', 'mslms-tranzila' ) . '</p>';
            },
            'mslms_trz' 
        );
        
        // Appearance Section
        add_settings_section( 
            'mslms_trz_appearance', 
            __( 'Appearance', 'mslms-tranzila' ), 
            function() {
                echo '<p>' . __( 'Customize payment form appearance', 'mslms-tranzila' ) . '</p>';
            },
            'mslms_trz' 
        );
        
        // Basic fields
        $basic_fields = [
            'enabled' => [
                'type' => 'checkbox',
                'label' => __( 'Enable Tranzila Gateway', 'mslms-tranzila' ),
                'section' => 'mslms_trz_main'
            ],
            'terminal' => [
                'type' => 'text',
                'label' => __( 'Terminal Name', 'mslms-tranzila' ),
                'section' => 'mslms_trz_main',
                'required' => true
            ],
            'mode' => [
                'type' => 'select',
                'label' => __( 'Mode', 'mslms-tranzila' ),
                'options' => [
                    'production' => __( 'Production', 'mslms-tranzila' ),
                    'sandbox' => __( 'Sandbox/Test', 'mslms-tranzila' )
                ],
                'default' => 'production',
                'section' => 'mslms_trz_main'
            ],
            'language' => [
                'type' => 'select',
                'label' => __( 'Language', 'mslms-tranzila' ),
                'options' => [
                    'il' => 'עברית',
                    'en' => 'English',
                    'ru' => 'Русский',
                    'ar' => 'العربية'
                ],
                'default' => 'il',
                'section' => 'mslms_trz_main'
            ],
            'currency' => [
                'type' => 'select',
                'label' => __( 'Currency', 'mslms-tranzila' ),
                'options' => [
                    '1' => 'ILS (₪)',
                    '2' => 'USD ($)',
                    '978' => 'EUR (€)',
                    '826' => 'GBP (£)',
                    '392' => 'JPY (¥)'
                ],
                'default' => '1',
                'section' => 'mslms_trz_main'
            ],
            'cred_type' => [
                'type' => 'select',
                'label' => __( 'Credit Type', 'mslms-tranzila' ),
                'options' => [
                    '1' => __( 'Regular', 'mslms-tranzila' ),
                    '6' => __( 'Credit', 'mslms-tranzila' ),
                    '8' => __( 'Installments', 'mslms-tranzila' )
                ],
                'default' => '1',
                'section' => 'mslms_trz_main'
            ],
            'maxpay' => [
                'type' => 'number',
                'label' => __( 'Max Installments', 'mslms-tranzila' ),
                'default' => '1',
                'attrs' => ['min' => 1, 'max' => 36],
                'section' => 'mslms_trz_main'
            ],
            'description' => [
                'type' => 'text',
                'label' => __( 'Checkout Description', 'mslms-tranzila' ),
                'default' => __( 'Secure payment via Tranzila', 'mslms-tranzila' ),
                'section' => 'mslms_trz_main'
            ]
        ];
        
        // Payment method fields
        $payment_method_fields = [
            'enable_creditcard' => [
                'type' => 'checkbox',
                'label' => __( 'Credit/Debit Cards', 'mslms-tranzila' ),
                'default' => '1',
                'section' => 'mslms_trz_methods',
                'description' => __( 'Standard credit and debit card payments', 'mslms-tranzila' )
            ],
            'enable_bit' => [
                'type' => 'checkbox',
                'label' => __( 'Bit', 'mslms-tranzila' ),
                'section' => 'mslms_trz_methods',
                'description' => __( 'Enable Bit payment method (requires Bit activation in Tranzila)', 'mslms-tranzila' )
            ],
            'enable_applepay' => [
                'type' => 'checkbox',
                'label' => __( 'Apple Pay', 'mslms-tranzila' ),
                'section' => 'mslms_trz_methods',
                'description' => __( 'Enable Apple Pay (requires Apple Pay activation in Tranzila)', 'mslms-tranzila' )
            ],
            'enable_googlepay' => [
                'type' => 'checkbox',
                'label' => __( 'Google Pay', 'mslms-tranzila' ),
                'section' => 'mslms_trz_methods',
                'description' => __( 'Enable Google Pay (requires Google Pay activation in Tranzila)', 'mslms-tranzila' )
            ],
            'enable_paypal' => [
                'type' => 'checkbox',
                'label' => __( 'PayPal', 'mslms-tranzila' ),
                'section' => 'mslms_trz_methods',
                'description' => __( 'Enable PayPal (requires PayPal activation in Tranzila)', 'mslms-tranzila' )
            ]
        ];
        
        // Appearance fields
        $appearance_fields = [
            'trBgColor' => [
                'type' => 'color',
                'label' => __( 'Background Color', 'mslms-tranzila' ),
                'default' => '#ffffff',
                'section' => 'mslms_trz_appearance'
            ],
            'trTextColor' => [
                'type' => 'color',
                'label' => __( 'Text Color', 'mslms-tranzila' ),
                'default' => '#000000',
                'section' => 'mslms_trz_appearance'
            ],
            'trButtonColor' => [
                'type' => 'color',
                'label' => __( 'Button Background Color', 'mslms-tranzila' ),
                'default' => '#0073aa',
                'section' => 'mslms_trz_appearance'
            ],
            'buttonTextColor' => [
                'type' => 'color',
                'label' => __( 'Button Text Color', 'mslms-tranzila' ),
                'default' => '#ffffff',
                'section' => 'mslms_trz_appearance'
            ],
            'hide_iframe_response' => [
                'type' => 'checkbox',
                'label' => __( 'Hide Technical Response', 'mslms-tranzila' ),
                'default' => '1',
                'section' => 'mslms_trz_appearance',
                'description' => __( 'Hide technical response page in iframe after payment', 'mslms-tranzila' )
            ],
            'loading_animation' => [
                'type' => 'select',
                'label' => __( 'Loading Animation', 'mslms-tranzila' ),
                'options' => [
                    'spinner' => __( 'Spinner', 'mslms-tranzila' ),
                    'dots' => __( 'Dots', 'mslms-tranzila' ),
                    'pulse' => __( 'Pulse', 'mslms-tranzila' ),
                    'none' => __( 'None', 'mslms-tranzila' )
                ],
                'default' => 'spinner',
                'section' => 'mslms_trz_appearance'
            ],
            'debug_mode' => [
                'type' => 'checkbox',
                'label' => __( 'Debug Mode', 'mslms-tranzila' ),
                'section' => 'mslms_trz_appearance',
                'description' => __( 'Enable debug logging and save all transaction data', 'mslms-tranzila' )
            ]
        ];
        
        // Register all fields
        $all_fields = array_merge($basic_fields, $payment_method_fields, $appearance_fields);
        
        foreach ( $all_fields as $key => $info ) {
            add_settings_field( 
                $key, 
                esc_html( $info['label'] ), 
                [ __CLASS__, 'render_field' ], 
                'mslms_trz', 
                $info['section'],
                [ 'key' => $key, 'info' => $info ]
            );
        }
    }
    
    public static function render_field( $args ) {
        $key = $args['key'];
        $info = $args['info'];
        $opts = get_option( self::OPT_KEY, [] );
        $value = isset( $opts[$key] ) ? $opts[$key] : ( $info['default'] ?? '' );
        $name = self::OPT_KEY . "[$key]";
        $id = 'mslms_trz_' . $key;
        
        switch ( $info['type'] ) {
            case 'checkbox':
                echo '<label for="' . esc_attr($id) . '">';
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' 
                     . checked( !empty($value), true, false ) . ' /> ';
                echo esc_html__( 'Enabled', 'mslms-tranzila' );
                echo '</label>';
                break;
                
            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="regular-text">';
                foreach ( $info['options'] as $opt_value => $opt_label ) {
                    echo '<option value="' . esc_attr($opt_value) . '" ' 
                         . selected( $value, $opt_value, false ) . '>';
                    echo esc_html( $opt_label );
                    echo '</option>';
                }
                echo '</select>';
                break;
                
            case 'number':
                $attrs = '';
                if ( !empty($info['attrs']) ) {
                    foreach ( $info['attrs'] as $attr => $attr_value ) {
                        $attrs .= ' ' . esc_attr($attr) . '="' . esc_attr($attr_value) . '"';
                    }
                }
                echo '<input type="number" id="' . esc_attr($id) . '" class="small-text" '
                     . 'name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $attrs . ' />';
                break;
                
            case 'color':
                echo '<input type="color" id="' . esc_attr($id) . '" '
                     . 'name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" /> ';
                echo '<code>' . esc_html($value) . '</code>';
                break;
                
            default:
                echo '<input type="text" id="' . esc_attr($id) . '" class="regular-text" '
                     . 'name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        }
        
        if ( !empty($info['description']) ) {
            echo '<p class="description">' . esc_html( $info['description'] ) . '</p>';
        }
        
        if ( !empty($info['required']) ) {
            echo ' <span style="color:red;">*</span>';
        }
    }
    
    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved successfully!', 'mslms-tranzila' ); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'mslms_trz_group' );
                do_settings_sections( 'mslms_trz' );
                submit_button();
                ?>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
                <h3><?php esc_html_e( 'Important URLs for Tranzila Configuration', 'mslms-tranzila' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'IPN/Notify URL', 'mslms-tranzila' ); ?></th>
                        <td>
                            <code><?php echo esc_url( rest_url( 'mslms-tranzila/v1/ipn' ) ); ?></code>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js(rest_url('mslms-tranzila/v1/ipn')); ?>')">📋 Copy</button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Transactions page
     */
    public static function transactions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filters
        $where = [];
        $where_values = [];
        
        if ( !empty($_GET['status']) ) {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($_GET['status']);
        }
        
        if ( !empty($_GET['search']) ) {
            $search = '%' . $wpdb->esc_like($_GET['search']) . '%';
            $where[] = '(order_id LIKE %s OR transaction_id LIKE %s OR customer_email LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        if ( !empty($_GET['date_from']) ) {
            $where[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if ( !empty($_GET['date_to']) ) {
            $where[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        $total_items = $where_values ? $wpdb->get_var($wpdb->prepare($total_query, $where_values)) : $wpdb->get_var($total_query);
        
        // Get transactions
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        if ( $where_values ) {
            $where_values[] = $per_page;
            $where_values[] = $offset;
            $transactions = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $transactions = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));
        }
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Tranzila Transactions', 'mslms-tranzila' ); ?>
                <button class="page-title-action" onclick="exportTransactions()">
                    <?php esc_html_e( 'Export CSV', 'mslms-tranzila' ); ?>
                </button>
            </h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="mslms-tranzila-transactions">
                    
                    <div class="alignleft actions">
                        <select name="status">
                            <option value=""><?php esc_html_e( 'All Statuses', 'mslms-tranzila' ); ?></option>
                            <option value="completed" <?php selected( isset($_GET['status']) && $_GET['status'] === 'completed' ); ?>>
                                <?php esc_html_e( 'Completed', 'mslms-tranzila' ); ?>
                            </option>
                            <option value="pending" <?php selected( isset($_GET['status']) && $_GET['status'] === 'pending' ); ?>>
                                <?php esc_html_e( 'Pending', 'mslms-tranzila' ); ?>
                            </option>
                            <option value="failed" <?php selected( isset($_GET['status']) && $_GET['status'] === 'failed' ); ?>>
                                <?php esc_html_e( 'Failed', 'mslms-tranzila' ); ?>
                            </option>
                        </select>
                        
                        <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e( 'From Date', 'mslms-tranzila' ); ?>">
                        
                        <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e( 'To Date', 'mslms-tranzila' ); ?>">
                        
                        <input type="text" name="search" value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e( 'Search...', 'mslms-tranzila' ); ?>">
                        
                        <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'mslms-tranzila' ); ?>">
                        
                        <?php if ( !empty($_GET['status']) || !empty($_GET['search']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) ) : ?>
                            <a href="?page=mslms-tranzila-transactions" class="button">
                                <?php esc_html_e( 'Clear Filters', 'mslms-tranzila' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Transactions Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="80"><?php esc_html_e( 'Order ID', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Transaction ID', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Customer', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Response', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Payment Method', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'mslms-tranzila' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $transactions ) : ?>
                        <?php foreach ( $transactions as $transaction ) : ?>
                            <?php
                            $response_data = json_decode($transaction->response_data, true);
                            $currency_symbols = ['1' => '₪', '2' => '$', '978' => '€', '826' => '£', '392' => '¥'];
                            $currency_symbol = $currency_symbols[$transaction->currency] ?? $transaction->currency;
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $transaction->order_id . '&action=edit' ) ); ?>">
                                            #<?php echo esc_html( $transaction->order_id ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <code><?php echo esc_html( $transaction->transaction_id ?: '-' ); ?></code>
                                </td>
                                <td>
                                    <?php 
                                    echo esc_html( $transaction->customer_name ?: '-' );
                                    if ( $transaction->customer_email ) {
                                        echo '<br><small>' . esc_html( $transaction->customer_email ) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $currency_symbol . ' ' . number_format($transaction->amount, 2) ); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = ucfirst($transaction->status);
                                    
                                    switch($transaction->status) {
                                        case 'completed':
                                            $status_class = 'status-completed';
                                            $status_text = '✅ ' . __('Completed', 'mslms-tranzila');
                                            break;
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            $status_text = '⏳ ' . __('Pending', 'mslms-tranzila');
                                            break;
                                        case 'failed':
                                            $status_class = 'status-failed';
                                            $status_text = '❌ ' . __('Failed', 'mslms-tranzila');
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($status_class); ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo esc_html( $transaction->response_code ?: '-' ); ?></code>
                                    <?php if ( $transaction->response_code && $transaction->response_code !== '000' ) : ?>
                                        <br><small><?php echo esc_html( self::get_response_message($transaction->response_code) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $payment_method = $response_data['payment_method'] ?? 
                                                     $response_data['cctype'] ?? 
                                                     $response_data['cardtype'] ?? 
                                                     '-';
                                    echo esc_html( $payment_method );
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $date = new DateTime($transaction->created_at);
                                    echo esc_html( $date->format('d/m/Y H:i') ); 
                                    ?>
                                </td>
                                <td>
                                    <button class="button button-small view-details" 
                                            data-id="<?php echo esc_attr($transaction->id); ?>">
                                        <?php esc_html_e( 'Details', 'mslms-tranzila' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">
                                <?php esc_html_e( 'No transactions found.', 'mslms-tranzila' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf( esc_html__( '%d items', 'mslms-tranzila' ), $total_items ); ?>
                    </span>
                    
                    <?php
                    $pagination_args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ];
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal for transaction details -->
        <div id="transaction-details-modal" class="trz-modal" style="display:none;">
            <div class="trz-modal-content">
                <span class="trz-modal-close" onclick="closeModal()">&times;</span>
                <h2><?php esc_html_e( 'Transaction Details', 'mslms-tranzila' ); ?></h2>
                <div id="transaction-details-content"></div>
                <button class="button button-primary" onclick="closeModal()"><?php esc_html_e( 'Close', 'mslms-tranzila' ); ?></button>
            </div>
        </div>
        
        <style>
            .status-completed { color: #46b450; font-weight: 600; }
            .status-pending { color: #ffb900; font-weight: 600; }
            .status-failed { color: #dc3232; font-weight: 600; }
            
            /* Modal styles - Fixed */
            .trz-modal {
                display: none; /* Hidden by default */
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
                z-index: 100000;
                align-items: center;
                justify-content: center;
            }
            
            .trz-modal.show {
                display: flex; /* Show when has 'show' class */
            }
            
            .trz-modal-content {
                background: white;
                padding: 30px;
                border-radius: 8px;
                max-width: 800px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                position: relative;
            }
            
            .trz-modal-close {
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                color: #999;
                line-height: 1;
            }
            
            .trz-modal-close:hover {
                color: #000;
            }
            
            #transaction-details-content table {
                width: 100%;
                margin: 20px 0;
            }
            
            #transaction-details-content table th {
                text-align: left;
                padding: 8px;
                background: #f5f5f5;
                font-weight: 600;
            }
            
            #transaction-details-content table td {
                padding: 8px;
                border-bottom: 1px solid #eee;
            }
            
            .tablenav.top {
                margin-bottom: 15px;
            }
            
            .alignleft.actions input,
            .alignleft.actions select {
                margin-right: 5px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // View details button
            $('.view-details').on('click', function() {
                var transactionId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'trz_get_transaction_details',
                        transaction_id: transactionId,
                        _ajax_nonce: '<?php echo wp_create_nonce('trz_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#transaction-details-content').html(response.data);
                            $('#transaction-details-modal').addClass('show');
                        }
                    }
                });
            });
            
            // Close modal on ESC key
            $(document).on('keyup', function(e) {
                if (e.key === "Escape") {
                    closeModal();
                }
            });
            
            // Close modal on background click
            $('#transaction-details-modal').on('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });
        
        function closeModal() {
            document.getElementById('transaction-details-modal').classList.remove('show');
        }
        
        function exportTransactions() {
            window.location.href = '<?php echo admin_url('admin-ajax.php?action=trz_export_transactions&_wpnonce=' . wp_create_nonce('trz_export')); ?>';
        }
        </script>
        <?php
    }
    
    /**
     * Statistics page
     */
    public static function statistics_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
        
        // Get statistics
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue
            FROM $table_name
        ");
        
        // Get monthly statistics
        $monthly_stats = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as revenue
            FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tranzila Statistics', 'mslms-tranzila' ); ?></h1>
            
            <!-- Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #666;"><?php esc_html_e( 'Total Transactions', 'mslms-tranzila' ); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #0073aa;">
                        <?php echo number_format($stats->total_transactions); ?>
                    </p>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #666;"><?php esc_html_e( 'Total Revenue', 'mslms-tranzila' ); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #46b450;">
                        ₪ <?php echo number_format($stats->total_revenue, 2); ?>
                    </p>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #666;"><?php esc_html_e( 'Success Rate', 'mslms-tranzila' ); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #46b450;">
                        <?php 
                        $success_rate = $stats->total_transactions > 0 
                            ? round(($stats->completed / $stats->total_transactions) * 100, 1) 
                            : 0;
                        echo $success_rate . '%';
                        ?>
                    </p>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #666;"><?php esc_html_e( 'Failed Transactions', 'mslms-tranzila' ); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #dc3232;">
                        <?php echo number_format($stats->failed); ?>
                    </p>
                </div>
            </div>
            
            <!-- Monthly Statistics -->
            <h2><?php esc_html_e( 'Monthly Statistics', 'mslms-tranzila' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Month', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Transactions', 'mslms-tranzila' ); ?></th>
                        <th><?php esc_html_e( 'Revenue', 'mslms-tranzila' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $monthly_stats as $month ) : ?>
                        <tr>
                            <td><?php echo esc_html( date('F Y', strtotime($month->month . '-01')) ); ?></td>
                            <td><?php echo number_format($month->transactions); ?></td>
                            <td>₪ <?php echo number_format($month->revenue, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for transaction details
     */
    public static function ajax_get_transaction_details() {
        check_ajax_referer( 'trz_admin_nonce' );
        
        if ( ! current_user_can('manage_options') ) {
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
        
        $transaction_id = intval($_POST['transaction_id']);
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $transaction_id
        ));
        
        if ( ! $transaction ) {
            wp_send_json_error( 'Transaction not found' );
        }
        
        $response_data = json_decode($transaction->response_data, true);
        
        ob_start();
        ?>
        <table>
            <tr>
                <th><?php esc_html_e( 'Order ID', 'mslms-tranzila' ); ?>:</th>
                <td>#<?php echo esc_html($transaction->order_id); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Transaction ID', 'mslms-tranzila' ); ?>:</th>
                <td><?php echo esc_html($transaction->transaction_id ?: '-'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Status', 'mslms-tranzila' ); ?>:</th>
                <td><?php echo esc_html(ucfirst($transaction->status)); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Amount', 'mslms-tranzila' ); ?>:</th>
                <td><?php echo esc_html(number_format($transaction->amount, 2) . ' ' . $transaction->currency); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Date', 'mslms-tranzila' ); ?>:</th>
                <td><?php echo esc_html($transaction->created_at); ?></td>
            </tr>
        </table>
        
        <h3><?php esc_html_e( 'Response Data from Tranzila', 'mslms-tranzila' ); ?></h3>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">
<?php echo esc_html(json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
        </pre>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success( $html );
    }
    
    /**
     * Export transactions to CSV
     */
    public static function ajax_export_transactions() {
        check_admin_referer( 'trz_export' );
        
        if ( ! current_user_can('manage_options') ) {
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mslms_tranzila_transactions';
        
        $transactions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=tranzila-transactions-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'Order ID',
            'Transaction ID',
            'Customer Name',
            'Customer Email',
            'Amount',
            'Currency',
            'Status',
            'Response Code',
            'Date',
            'Payment Method',
            'Card Type',
            'Card Last 4'
        ]);
        
        // Data
        foreach ( $transactions as $transaction ) {
            $response_data = json_decode($transaction->response_data, true);
            
            fputcsv($output, [
                $transaction->order_id,
                $transaction->transaction_id,
                $transaction->customer_name,
                $transaction->customer_email,
                $transaction->amount,
                $transaction->currency,
                $transaction->status,
                $transaction->response_code,
                $transaction->created_at,
                $response_data['payment_method'] ?? '',
                $response_data['cctype'] ?? '',
                $response_data['ccnumber'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get response message by code
     */
    private static function get_response_message( $code ) {
        $messages = [
            '000' => __( 'Transaction approved', 'mslms-tranzila' ),
            '001' => __( 'Card blocked', 'mslms-tranzila' ),
            '002' => __( 'Card stolen', 'mslms-tranzila' ),
            '003' => __( 'Contact credit company', 'mslms-tranzila' ),
            '004' => __( 'Refusal', 'mslms-tranzila' ),
            '005' => __( 'Forged card', 'mslms-tranzila' ),
            '006' => __( 'Invalid CVV', 'mslms-tranzila' ),
            '033' => __( 'Card expired', 'mslms-tranzila' ),
            '036' => __( 'Card restricted', 'mslms-tranzila' ),
            '039' => __( 'Invalid card number', 'mslms-tranzila' ),
            '051' => __( 'Insufficient funds', 'mslms-tranzila' ),
            '057' => __( 'Card not permitted', 'mslms-tranzila' ),
            '061' => __( 'Exceeds limit', 'mslms-tranzila' ),
            '065' => __( 'Invalid transaction', 'mslms-tranzila' ),
        ];
        
        return $messages[$code] ?? __( 'Unknown error', 'mslms-tranzila' );
    }
    
    public static function filter_payment_method_labels( $labels ) {
        $opts = get_option( self::OPT_KEY, [] );
        
        $methods = [];
        if ( !empty($opts['enable_creditcard']) ) $methods[] = 'Credit/Debit';
        if ( !empty($opts['enable_bit']) ) $methods[] = 'Bit';
        if ( !empty($opts['enable_applepay']) ) $methods[] = 'Apple Pay';
        if ( !empty($opts['enable_googlepay']) ) $methods[] = 'Google Pay';
        if ( !empty($opts['enable_paypal']) ) $methods[] = 'PayPal';
        
        if ( empty($methods) ) $methods[] = 'Credit/Debit';
        
        $labels['tranzila'] = implode(' / ', $methods) . ' (Tranzila)';
        
        return $labels;
    }
    
    public static function sync_into_ms_settings() {
        $opts = get_option( self::OPT_KEY, [] );
        $ms = get_option( 'stm_lms_settings', [] );
        
        if ( ! is_array($ms) ) $ms = [];
        if ( empty($ms['payment_methods']) ) $ms['payment_methods'] = [];
        
        $ms['payment_methods']['tranzila'] = [
            'enabled' => !empty($opts['enabled']) ? 1 : 0,
            'payment_description' => isset($opts['description']) ? $opts['description'] : __( 'Secure payment via Tranzila', 'mslms-tranzila' ),
            'fields' => [],
        ];
        
        update_option( 'stm_lms_settings', $ms );
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function admin_scripts( $hook ) {
        if ( strpos($hook, 'mslms-tranzila') !== false ) {
            wp_enqueue_style( 'mslms-trz-admin', MSLMS_TRZ_URL . 'assets/admin.css', [], MSLMS_TRZ_VER );
        }
    }
}