/**
 * Lunu payment method model
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
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push({
            type: 'lunu_merchant',
            component: 'Lunu_Merchant/js/view/payment/method-renderer/lunu-method'
        });
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
