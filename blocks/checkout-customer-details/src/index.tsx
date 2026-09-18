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
                <PanelBody title={__('Layout', 'jankx')}>
                    <SelectControl
                        label={__('Column', 'jankx')}
                        value={attributes.jankxCheckoutColumn}
                        options={[
                            { value: 'customer', label: __('Customer column', 'jankx') },
                            { value: 'summary', label: __('Summary column', 'jankx') },
                            { value: '', label: __('Full width', 'jankx') },
                        ]}
                        onChange={(jankxCheckoutColumn) => setAttributes({ jankxCheckoutColumn })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <h2 className="jankx-section-title">{__('Billing details', 'jankx')}</h2>
                <div className="jankx-field">
                    <label>{__('Full name', 'jankx')} <span className="jankx-required">*</span></label>
                    <input type="text" className="jankx-input" placeholder={__('Full name', 'jankx')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Email', 'jankx')} <span className="jankx-required">*</span></label>
                    <input type="email" className="jankx-input" placeholder={__('Email', 'jankx')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Phone', 'jankx')}</label>
                    <input type="tel" className="jankx-input" placeholder={__('Phone', 'jankx')} disabled />
                </div>
                <div className="jankx-field">
                    <label>{__('Address', 'jankx')}</label>
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