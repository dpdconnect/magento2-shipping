define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function ($, wrapper, quote, globalMessageList, $t) {
    'use strict';


     return function (target) {
         return target.extend({
         validate: function () {
                let result = this._super();
                var selectedShippingMethod = quote.shippingMethod();
                if (selectedShippingMethod.carrier_code === 'dpdpickup' && selectedShippingMethod.method_code === 'dpdpickup') {
                    var parcelShopId = $('.parcelshopId').val();
                    if (!parcelShopId) {
                        globalMessageList.addErrorMessage({ message: $t('You must select a parcelshop')});
                        this.source.set('params.invalid', true);
                        return false;
                    }
                }
                return result;
             }
         });
     };
});

