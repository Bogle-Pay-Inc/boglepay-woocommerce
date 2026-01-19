/**
 * Bogle Pay Block Checkout Support
 * 
 * Registers the Bogle Pay payment method for WooCommerce Block Checkout
 */
( function() {
    'use strict';

    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { decodeEntities } = wp.htmlEntities;
    const { createElement } = wp.element;

    const settings = getSetting( 'boglepay_data', {} );

    const defaultLabel = 'Pay with Card';
    const label = decodeEntities( settings.title ) || defaultLabel;

    /**
     * Content component - what shows when payment method is selected
     */
    const Content = () => {
        return createElement(
            'div',
            { className: 'boglepay-block-content' },
            decodeEntities( settings.description || '' )
        );
    };

    /**
     * Label component - the payment method title with icon
     */
    const Label = ( props ) => {
        const { PaymentMethodLabel } = props.components;
        
        const icon = settings.icon ? createElement(
            'img',
            {
                src: settings.icon,
                alt: label,
                style: { 
                    height: '24px', 
                    marginRight: '8px',
                    verticalAlign: 'middle'
                }
            }
        ) : null;

        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center' } },
            icon,
            createElement( PaymentMethodLabel, { text: label } )
        );
    };

    /**
     * Bogle Pay payment method configuration
     */
    const boglepayPaymentMethod = {
        name: 'boglepay',
        label: createElement( Label, null ),
        content: createElement( Content, null ),
        edit: createElement( Content, null ),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    };

    // Register the payment method
    registerPaymentMethod( boglepayPaymentMethod );

} )();
