define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules', 
        '../model/bxexpress-validation',
        '../model/bxexpress-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        shippingRatesValidator,
        shippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('bxexpress', shippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('bxexpress', shippingRatesValidationRules);
        return Component;
    }
);
