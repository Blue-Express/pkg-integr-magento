define([
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'domReady'
], function (ko, Component, modal, $, quote, rateRegistry ) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'BlueExpress_Shipping/shipping/pudo'
        },

        initialize: function () {
            this._super();
            var self = this;
            self.customShippingOption = ko.observable('bx_delivery');
	    self.isPudoEnabled = ko.observable(window.checkoutConfig.bxpudo.pudo_enabled);
	    self.bxKeyGoogle = ko.observable(window.checkoutConfig.bxpudo.key_google);
	    self.iframeUrl = ko.observable('https://widget-pudo.qa.blue.cl/?key=' + encodeURIComponent(self.bxKeyGoogle()));

            self.customShippingOption.subscribe(function (newValue) {
                if (newValue === 'bx_pudo') {
			self.showModal();
                }
            });
      	},

        showModal: function () {
	     var apiUrl = '/rest/V1/directory/countries/CL';

	     $.ajax({
                url: apiUrl,
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    window.addEventListener("message", (event) => {
                        var bxPudo = event.data.payload;
                        //Prepare field
			var eventoKeyup = new Event('keyup');
			var eventChange = new Event('change');

			var street = document.querySelector('[name="street[0]"]');
                        street.value =  bxPudo.location.street_name +' '+ bxPudo.location.street_number + ' - ' +  bxPudo.agency_id;
			street.dispatchEvent(eventoKeyup);

			var city = document.querySelector('[name="city"]');
                        city.value = bxPudo.location.city_name;
			city.dispatchEvent(eventoKeyup);


                        var regions = data.available_regions;
                        var regionsPudo = ['Aysén', 'Antofagasta', 'Arica y Parinacota', 'Araucanía', 'Atacama', 'Bío - Bío', 'Coquimbo', 'Libertador General Bernardo O`Higgins', 'Los Lagos', 'Los Ríos', 'Magallanes y la Antartica Chilena', 'Maule', 'Ñuble', 'Metropolitana de Santiago', 'Tarapacá','Valparaiso' ,''];
                        var elem = regionsPudo.indexOf(bxPudo.location.state_name);
			var regionId = document.querySelector('[name="region_id"]');
			regionId.value = regions[elem].id;
			regionId.dispatchEvent(eventChange);

                        //submit shipping pudo
                        var address = quote.shippingAddress();

                        address.street = [bxPudo.location.street_name +' '+ bxPudo.location.street_number + ' - ' +  bxPudo.agency_id, '' ];
                        address.city =  bxPudo.location.city_name;
                        address.state = regions[elem].name;
                        address.region = regions[elem].name;
			address.regionId = regions[elem].id;
                        address.regionCode = regions[elem].code;
                        address.region_id = regions[elem].id;
                        address.region_code = regions[elem].code;

                        //save data pudo
                        rateRegistry.set(address.getKey(), null);
                        rateRegistry.set(address.getCacheKey(), null);
                        quote.shippingAddress(address);

                    });
                },
                error: function (error) {
                    console.error('Error al obtener las regiones: ' + error);
                }
            });

            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: true,
                title: 'Puntos de Retiro Blue Express',
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'action-primary action-accept',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };

            modal(options, $('#bx-modal-pudo'));
            $('#bx-modal-pudo').modal('openModal');
        }
    });
});
