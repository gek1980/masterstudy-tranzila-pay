/**
 * Tranzila Checkout JavaScript
 * Handles payment flow and status updates
 */

(function($) {
    'use strict';

    // Tranzila Checkout Handler
    window.MSLMSTranzilaCheckout = {
        
        // Configuration
        config: {
            pollInterval: 2000,
            maxPolls: 150, // 5 minutes max
            debug: false
        },
        
        // Current state
        state: {
            orderId: null,
            orderKey: null,
            pollCount: 0,
            polling: false
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initializeFromURL();
            
            if (this.config.debug) {
                console.log('Tranzila Checkout initialized');
            }
        },
        
        // Bind events
        bindEvents: function() {
            // Handle payment method selection on checkout
            $(document).on('change', 'input[name="payment_method"]', this.handlePaymentMethodChange);
            
            // Handle checkout form submission
            $(document).on('submit', '.stm-lms-checkout__form', this.handleCheckoutSubmit);
            
            // Handle payment status updates
            $(document).on('tranzila:payment:success', this.handlePaymentSuccess);
            $(document).on('tranzila:payment:failed', this.handlePaymentFailed);
        },
        
        // Initialize from URL parameters
        initializeFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('order_id')) {
                this.state.orderId = urlParams.get('order_id');
            }
            
            if (urlParams.has('key')) {
                this.state.orderKey = urlParams.get('key');
            }
            
            if (urlParams.has('payment_failed')) {
                this.showError(mslms_trz.strings.failed);
            }
        },
        
        // Handle payment method change
        handlePaymentMethodChange: function(e) {
            const method = $(e.target).val();
            
            if (method === 'tranzila') {
                // Show Tranzila-specific information
                $('.tranzila-payment-info').slideDown();
            } else {
                $('.tranzila-payment-info').slideUp();
            }
        },
        
        // Handle checkout form submission
        handleCheckoutSubmit: function(e) {
            const $form = $(e.target);
            const paymentMethod = $form.find('input[name="payment_method"]:checked').val();
            
            if (paymentMethod === 'tranzila') {
                // The form will submit normally and redirect to payment page
                MSLMSTranzilaCheckout.showLoading(mslms_trz.strings.processing);
            }
        },
        
        // Start polling for payment status
        startPolling: function() {
            if (this.state.polling) {
                return;
            }
            
            this.state.polling = true;
            this.state.pollCount = 0;
            this.pollStatus();
        },
        
        // Poll payment status
        pollStatus: function() {
            if (!this.state.polling || this.state.pollCount >= this.config.maxPolls) {
                this.state.polling = false;
                return;
            }
            
            const self = this;
            
            $.ajax({
                url: mslms_trz.rest_url + 'status',
                method: 'GET',
                data: {
                    order_id: this.state.orderId,
                    key: this.state.orderKey
                },
                success: function(response) {
                    if (response.ok) {
                        if (response.status === 'completed') {
                            self.handlePaymentSuccess();
                        } else if (response.status === 'failed') {
                            self.handlePaymentFailed();
                        } else {
                            // Continue polling
                            self.state.pollCount++;
                            setTimeout(function() {
                                self.pollStatus();
                            }, self.config.pollInterval);
                        }
                    }
                },
                error: function() {
                    // Retry on error
                    self.state.pollCount++;
                    setTimeout(function() {
                        self.pollStatus();
                    }, self.config.pollInterval * 2);
                }
            });
        },
        
        // Handle payment success
        handlePaymentSuccess: function() {
            this.state.polling = false;
            this.showSuccess(mslms_trz.strings.completed);
            
            // Trigger MasterStudy success event if available
            if (typeof STM_LMS !== 'undefined' && STM_LMS.checkout) {
                STM_LMS.checkout.payment_completed();
            }
        },
        
        // Handle payment failure
        handlePaymentFailed: function() {
            this.state.polling = false;
            this.showError(mslms_trz.strings.failed);
        },
        
        // Show loading state
        showLoading: function(message) {
            const $container = this.getMessageContainer();
            
            $container.html(
                '<div class="tranzila-message loading">' +
                    '<div class="spinner"></div>' +
                    '<span>' + message + '</span>' +
                '</div>'
            );
        },
        
        // Show success message
        showSuccess: function(message) {
            const $container = this.getMessageContainer();
            
            $container.html(
                '<div class="tranzila-message success">' +
                    '<svg class="icon" viewBox="0 0 24 24">' +
                        '<path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>' +
                    '</svg>' +
                    '<span>' + message + '</span>' +
                '</div>'
            );
        },
        
        // Show error message
        showError: function(message) {
            const $container = this.getMessageContainer();
            
            $container.html(
                '<div class="tranzila-message error">' +
                    '<svg class="icon" viewBox="0 0 24 24">' +
                        '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>' +
                    '</svg>' +
                    '<span>' + message + '</span>' +
                '</div>'
            );
        },
        
        // Get or create message container
        getMessageContainer: function() {
            let $container = $('#tranzila-messages');
            
            if (!$container.length) {
                $container = $('<div id="tranzila-messages"></div>');
                
                // Try to insert in checkout form
                const $checkout = $('.stm-lms-checkout__form');
                if ($checkout.length) {
                    $checkout.prepend($container);
                } else {
                    $('body').prepend($container);
                }
            }
            
            return $container;
        },
        
        // Validate transaction with backend
        validateTransaction: function(transactionId) {
            const self = this;
            
            $.ajax({
                url: mslms_trz.rest_url + 'validate',
                method: 'POST',
                data: {
                    order_id: this.state.orderId,
                    transaction_id: transactionId
                },
                success: function(response) {
                    if (response.ok && response.valid) {
                        self.handlePaymentSuccess();
                    } else {
                        self.handlePaymentFailed();
                    }
                },
                error: function() {
                    self.showError(mslms_trz.strings.error);
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MSLMSTranzilaCheckout.init();
    });
    
    // Also initialize on MasterStudy checkout ready
    $(document).on('stm_lms_checkout_ready', function() {
        MSLMSTranzilaCheckout.init();
    });

})(jQuery);

// Add CSS styles
(function() {
    const styles = `
        #tranzila-messages {
            margin: 20px 0;
        }
        
        .tranzila-message {
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            font-size: 16px;
            margin-bottom: 15px;
            animation: slideIn 0.3s ease;
        }
        
        .tranzila-message.loading {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #90caf9;
        }
        
        .tranzila-message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
        }
        
        .tranzila-message.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }
        
        .tranzila-message .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #90caf9;
            border-top-color: #1976d2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        .tranzila-message .icon {
            width: 24px;
            height: 24px;
            margin-right: 10px;
        }
        
        .tranzila-payment-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            display: none;
        }
        
        .tranzila-payment-info h4 {
            margin: 0 0 10px;
            color: #333;
        }
        
        .tranzila-payment-info ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .tranzila-payment-info li {
            color: #666;
            margin: 5px 0;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
})();