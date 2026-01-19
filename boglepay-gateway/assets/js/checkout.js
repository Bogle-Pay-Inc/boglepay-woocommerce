/**
 * Bogle Pay Gateway - Checkout JavaScript
 *
 * @package BoglePay_Gateway
 */

(function($) {
    'use strict';

    /**
     * Bogle Pay Checkout Handler
     */
    var BoglePay = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.updatePaymentMethodUI();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Update UI when payment method changes
            $(document.body).on('change', 'input[name="payment_method"]', this.updatePaymentMethodUI.bind(this));
            
            // Handle checkout form submission
            $(document.body).on('checkout_place_order_boglepay', this.onCheckoutSubmit.bind(this));
            
            // Add secure badge after payment method description loads
            $(document.body).on('updated_checkout', this.addSecureBadge.bind(this));
        },

        /**
         * Update payment method UI based on selection
         */
        updatePaymentMethodUI: function() {
            var $boglePay = $('.wc_payment_method.payment_method_boglepay');
            var isSelected = $('#payment_method_boglepay').is(':checked');
            
            $boglePay.toggleClass('checked', isSelected);
        },

        /**
         * Handle checkout form submission
         *
         * @param {Event} e Submit event
         * @return {boolean}
         */
        onCheckoutSubmit: function(e) {
            // Add loading state
            var $form = $('form.checkout');
            $form.addClass('boglepay-loading');
            
            // Show redirect notice
            this.showRedirectNotice();
            
            // Allow form submission to proceed
            // The gateway will handle the redirect
            return true;
        },

        /**
         * Show redirect notice
         */
        showRedirectNotice: function() {
            var $paymentBox = $('.payment_method_boglepay .payment_box');
            
            // Don't add if already exists
            if ($paymentBox.find('.boglepay-redirect-processing').length) {
                return;
            }
            
            var notice = '<div class="boglepay-redirect-processing" style="margin-top: 16px; padding: 12px; background: #eff6ff; border-radius: 6px; text-align: center;">' +
                '<div class="boglepay-spinner" style="margin: 0 auto 12px;"></div>' +
                '<p style="margin: 0; color: #1e40af; font-size: 14px;">Redirecting to secure payment page...</p>' +
                '</div>';
            
            $paymentBox.append(notice);
        },

        /**
         * Add secure badge to payment description
         */
        addSecureBadge: function() {
            var $paymentBox = $('.payment_method_boglepay .payment_box');
            
            // Don't add if already exists
            if ($paymentBox.find('.boglepay-secure-badge').length) {
                return;
            }
            
            // Add redirect notice
            var redirectNotice = '<div class="boglepay-redirect-notice">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">' +
                '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />' +
                '</svg>' +
                '<p>You will be redirected to a secure payment page to complete your purchase.</p>' +
                '</div>';
            
            // Add secure badge
            var secureBadge = '<div class="boglepay-secure-badge">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">' +
                '<path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />' +
                '</svg>' +
                'Secure 256-bit SSL encryption' +
                '</div>';
            
            $paymentBox.append(redirectNotice);
            $paymentBox.append(secureBadge);
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            var $paymentBox = $('.payment_method_boglepay .payment_box');
            
            // Remove existing errors
            $paymentBox.find('.boglepay-error').remove();
            
            var error = '<div class="boglepay-error">' + message + '</div>';
            $paymentBox.prepend(error);
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $paymentBox.offset().top - 100
            }, 300);
        },

        /**
         * Clear error messages
         */
        clearErrors: function() {
            $('.payment_method_boglepay .boglepay-error').remove();
        }
    };

    /**
     * Admin settings handler
     */
    var BoglepayAdmin = {
        /**
         * Initialize admin scripts
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind admin events
         */
        bindEvents: function() {
            // Copy webhook URL button
            $(document).on('click', '.boglepay-copy-btn', this.copyWebhookUrl.bind(this));
            
            // Toggle API key visibility
            $(document).on('click', '.boglepay-toggle-key', this.toggleKeyVisibility.bind(this));
        },

        /**
         * Copy webhook URL to clipboard
         *
         * @param {Event} e Click event
         */
        copyWebhookUrl: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var $url = $btn.siblings('.boglepay-webhook-url');
            var url = $url.text();
            
            // Copy to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    $btn.addClass('copied').text('Copied!');
                    setTimeout(function() {
                        $btn.removeClass('copied').text('Copy');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                document.execCommand('copy');
                $temp.remove();
                
                $btn.addClass('copied').text('Copied!');
                setTimeout(function() {
                    $btn.removeClass('copied').text('Copy');
                }, 2000);
            }
        },

        /**
         * Toggle API key visibility
         *
         * @param {Event} e Click event
         */
        toggleKeyVisibility: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var $input = $btn.siblings('input');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('Hide');
            } else {
                $input.attr('type', 'password');
                $btn.text('Show');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Initialize checkout handler if on checkout page
        if ($('.woocommerce-checkout').length) {
            BoglePay.init();
        }
        
        // Initialize admin handler if on admin page
        if ($('.woocommerce_page_wc-settings').length) {
            BoglepayAdmin.init();
        }
    });

    // Also initialize on updated_checkout event
    $(document.body).on('updated_checkout', function() {
        BoglePay.updatePaymentMethodUI();
    });

})(jQuery);
