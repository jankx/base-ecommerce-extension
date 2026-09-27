import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'jankx-checkout-section jankx-checkout-section--editor',
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Layout', 'base-ecommerce')}>
                    <SelectControl
                        label={__('Column', 'base-ecommerce')}
                        value={attributes.jankxCheckoutColumn}
                        options={[
                            { value: 'customer', label: __('Customer column', 'base-ecommerce') },
                            { value: 'summary', label: __('Summary column', 'base-ecommerce') },
                            { value: '', label: __('Full width', 'base-ecommerce') },
                        ]}
                        onChange={(jankxCheckoutColumn) => setAttributes({ jankxCheckoutColumn })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="jankx-checkout-error" role="alert">
                    {__('Place order', 'base-ecommerce')}
                </div>
                <button
                    type="button"
                    className="jankx-btn jankx-btn-primary jankx-btn-place-order"
                    disabled
                >
                    {__('Place order', 'base-ecommerce')}
                </button>
            </div>
        </>
    );
}

function Save() {
    return null;
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});