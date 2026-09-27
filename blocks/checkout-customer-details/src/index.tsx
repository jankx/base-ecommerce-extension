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
                <h2 className="jankx-section-title">{__('Billing details', 'base-ecommerce')}</h2>
                <div className="jankx-field">
                    <label>{__('Full name', 'base-ecommerce')} <span className="jankx-required">*</span></label>
                    <input type="text" className="jankx-input" placeholder={__('Full name', 'base-ecommerce')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Email', 'base-ecommerce')} <span className="jankx-required">*</span></label>
                    <input type="email" className="jankx-input" placeholder={__('Email', 'base-ecommerce')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Phone', 'base-ecommerce')}</label>
                    <input type="tel" className="jankx-input" placeholder={__('Phone', 'base-ecommerce')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Address', 'base-ecommerce')}</label>
                    <textarea className="jankx-input" rows="3" disabled></textarea>
                </div>
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