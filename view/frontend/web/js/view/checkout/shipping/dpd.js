/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2019  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/sidebar',
    'Magento_Checkout/js/view/shipping-information/address-renderer/default',
    'mage/translate',
    'Magento_Checkout/js/model/shipping-rate-processor/new-address',
    'Magento_Checkout/js/model/shipping-rate-processor/customer-address',
    'Magento_Checkout/js/model/shipping-rate-registry',
    '!domReady'
], function ($, ko, Component, quote, shippingService, checkoutData, stepNavigator, sidebarMode, addressRenderer, translate, defaultProcessor, customerAddressProcessor, rateRegistry) {
    'use strict';

    return Component.extend({
        defaults: {
            isVisible: false,
            interval: null,
        },

        addRow: function () {
            var method = quote.shippingMethod();

            if (null !== method && method.carrier_code === 'dpd' && method.method_code === 'dpd') {
                if (0 === $('#dpd_carrier_container').length) {
                    if (0 !== $('#label_method_dpd_dpd').length) {
                        var colspan = $('#label_method_dpd_dpd').parent().children().length;

                        // Check if we need to show a selectbox
                        var shippingProductSelectBox = '';
                        if (1 < window.checkoutConfig.dpd_carrier_available_shipping_products.length) {
                            shippingProductSelectBox = '<select id="dpd_carrier_product">';
                            for (var i = 0; i < window.checkoutConfig.dpd_carrier_available_shipping_products.length; i++) {
                                var product = window.checkoutConfig.dpd_carrier_available_shipping_products[i];
                                shippingProductSelectBox += '<option value="' + product['code'] + '"';
                                if (window.checkoutConfig.dpd_carrier_shipping_selected_product === product['code']) {
                                    shippingProductSelectBox += ' selected="selected"';
                                }
                                shippingProductSelectBox += '>' + product['title'] + '</option>';
                            }
                            shippingProductSelectBox += '</select>';
                        } else {
                            shippingProductSelectBox = window.checkoutConfig.dpd_carrier_available_shipping_products[0].title;
                        }

                        var row = $(
                            '<tr class="row">' +
                            '<td class="col" colspan="' + colspan + '" id="dpd_carrier_container">' +
                            shippingProductSelectBox +
                            '</td>' +
                            '</tr>');

                        row.insertAfter($('#label_method_dpd_dpd').parent());

                        $('body').off('change', '#dpd_carrier_product');
                        $('body').on('change', '#dpd_carrier_product', function(e) {
                            var el = $(this);

                            $.ajax({
                                url: window.checkoutConfig.dpd_carrier_save_url,
                                method: 'POST',
                                data: {
                                    shippingProduct: $(this).val()
                                },
                                success: function() {
                                    window.checkoutConfig.dpd_carrier_shipping_selected_product = $(el).val();
                                    var processors = [];

                                    rateRegistry.set(quote.shippingAddress().getCacheKey(), null);

                                    processors.default =  defaultProcessor;
                                    processors['customer-address'] = customerAddressProcessor;

                                    var type = quote.shippingAddress().getType();
                                    if (processors[type]) {
                                        processors[type].getRates(quote.shippingAddress());
                                    } else {
                                        processors.default.getRates(quote.shippingAddress());
                                    }
                                }
                            });
                        });
                    }
                }
            } else {
                $('#dpd_carrier_container').parent().remove();
            }

            return (!(method === null)) ? (method.method_code + '_' + method.carrier_code) : null;
        },

        initObservable: function () {
            this._super().observe([
                'pickupAddresses',
                'postalCode',
                'city',
                'countryCode',
                'street',
                'hasAddress',
                'selectedOption',
            ]);

            this.selectedMethod = ko.computed(this.addRow, this);

            var that = this;
            this.interval = setInterval(function() {
                if (0 !== $('#label_method_dpd_dpd').length) {
                    clearInterval(that.interval);
                    that.addRow();
                }
            }, 100);

            return this;
        }
    });
});
