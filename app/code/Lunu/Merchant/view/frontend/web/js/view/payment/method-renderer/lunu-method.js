/**
 * Lunu JS
 *
 * @category    Lunu
 * @package     Lunu_Merchant
 * @author      Lunu Solutions GmbH
 * @copyright   Lunu Solutions GmbH (https://lunu.io)
 */
 /*browser:true*/
 /*global define*/
 define(
     [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
    ],
    function (
        $,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        additionalValidators,
        url
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Lunu_Merchant/payment/lunu-form'
            },
            placeOrder: function(data, event) {
                event && event.preventDefault();

                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && self.validate() && additionalValidators.validate()) {
                    self.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(self.getData(), false, self.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(self.afterPlaceOrder.bind(self));
                    return true;
                }
                return false;
            },
            selectPaymentMethod: function() {
                var self = this;
                selectPaymentMethodAction(self.getData());
                checkoutData.setSelectedPaymentMethod(self.item.method);
                return true;
            },
            afterPlaceOrder: function (quoteId) {
                $.ajax({
                    url: url.build('lunu/payment/placeOrder'),
                    type: 'POST',
                    dataType: 'json',
                    data: {quote_id: quoteId}
                }).done(function(response) {
                    var payment_url = response && response.payment_url;
                    if (payment_url) {
                        window.location.replace(payment_url);
                        return;
                    }
                    alert('Error\n' + response && response.reason || '');
                    // window.location.replace('/checkout/cart');
                });
            }
        });
    }
);
