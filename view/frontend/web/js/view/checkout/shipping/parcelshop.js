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
    'mage/translate'
], function ($, ko, Component, quote, shippingService, checkoutData, stepNavigator, sidebarMode, addressRenderer) {
    'use strict';

    return Component.extend({
        defaults: {
            isVisible: false,
            template: 'DpdConnect_Shipping/ShippingInfo',
            interval: null,
        },

        addRow: function () {
            var method = quote.shippingMethod();

            if (!(method === null) && method.carrier_code === 'dpdpickup' && method.method_code === 'dpdpickup') {
                if ($('#dpd_parcelshop_container').length === 0) {
                    if (!($('#label_method_dpdpickup_dpdpickup').length === 0)) {
                        var colspan = $('#label_method_dpdpickup_dpdpickup').parent().children().length;

                        var row = $(
                            '<tr class="row">' +
                            '<td class="col" colspan="' + colspan + '" id="dpd_parcelshop_container">' +
                            '<div id="dpd-connect-map-container" style="width: '+ window.checkoutConfig.dpd_googlemaps_width +'px; height: '+window.checkoutConfig.dpd_googlemaps_height+'px; display: none;"></div>\n' +
                            '<div id="dpd-connect-selected-container" style="display: none;">\n' +
                            '</div>\n' +
                            '</td>' +
                            '</tr>');

                        row.insertAfter($('#label_method_dpdpickup_dpdpickup').parent());

                        $('body').off('.dpd_connect_change_parcelshop')
                            .on('click', '.dpd_connect_change_parcelshop', (event) => {
                                event.preventDefault();

                                sessionStorage.removeItem('selectedParcelshop');
                                this.showMap();
                            });

                        var parcelshop = sessionStorage.getItem('selectedParcelshop');
                        if (parcelshop && JSON.parse(parcelshop).isoAlpha2 === quote.shippingAddress().countryId) {
                            this.selectParcelShop(JSON.parse(parcelshop));
                        } else {
                            sessionStorage.removeItem('selectedParcelshop');
                            this.showMap();
                        }
                    }
                }
            } else {
                $('#dpd_parcelshop_container').parent().remove();
            }

            return (!(method === null)) ? (method.method_code + '_' + method.carrier_code) : null;
        },


        showMap: function () {
            const shippingAddress = quote.shippingAddress();

            if (!shippingAddress.postcode || !shippingAddress.countryId) {
                $('#dpd-connect-map-container').html($.mage.__('No address entered. Please enter your address and try again.'));
                $('#dpd-connect-map-container').show();
                return;
            }

            DPDConnect.onParcelshopSelected = this.selectParcelShop;

            const locale = window.checkoutConfig.dpd_locale || 'nl';
            const searchAddress = shippingAddress.street[0] + ' ' + shippingAddress.postcode + ' ' + shippingAddress.city + ' ' + shippingAddress.countryId;
            if (window.checkoutConfig.dpd_parcelshop_use_dpd_key) {
                DPDConnect.show(window.checkoutConfig.dpd_parcelshop_token, searchAddress, locale);
            } else {
                DPDConnect.show(window.checkoutConfig.dpd_parcelshop_token, searchAddress, locale, window.checkoutConfig.dpd_parcelshop_google_key);
            }
        },


        initObservable: function () {
            this._super().observe([
                'pickupAddresses',
                'postalCode',
                'city',
                'countryCode',
                'street',
                'hasAddress',
                'selectedOption'
            ]);

            this.selectedMethod = ko.computed(this.addRow, this);

            var that = this;
            this.interval = setInterval(function() {
                if (0 !== $('#label_method_dpdpickup_dpdpickup').length) {
                    clearInterval(that.interval);
                    that.addRow();
                }
            }, 100);

            return this;
        },

        selectParcelShop: function (parcelshop) {
            sessionStorage.setItem('selectedParcelshop', JSON.stringify(parcelshop));
            window.dpdShippingAddress = parcelshop;

            if (parcelshop.isoAlpha2 === quote.shippingAddress().countryId) {
                var options = {
                    method: 'POST',
                    showLoader: true,
                    url: window.checkoutConfig.dpd_parcelshop_save_url,
                    data: parcelshop
                };

                $.ajax(options).done((response) => {
                    $('#dpd-connect-selected-container').html(response);

                    $('#dpd_company').html(parcelshop.company);
                    $('#dpd_street').html(parcelshop.street + ' ' + parcelshop.houseNo);
                    $('#dpd_zipcode_and_city').html(parcelshop.zipCode + ' ' + parcelshop.city);
                    $('#dpd_country').html(parcelshop.isoAlpha2);
                    $('.dpd-shipping-information').show();
                    $('#dpd-connect-selected-container').show();
                });
            } else {
                sessionStorage.removeItem('selectedParcelshop');
                $('#dpd-connect-map-container').html($.mage.__('Country of selected parcel shop is not equal to selected country. Please enter a valid address and try again.'));
                $('#dpd-connect-map-container').show();
                this.showMap();
            }
        }
    });
});
