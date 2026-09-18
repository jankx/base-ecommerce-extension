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
                <div className="jankx-credits-form">
                    <label className="jankx-credits-toggle-label">
                        <input type="checkbox" className="jankx-credits-toggle" value="1" disabled />
                        <span>{__('Dùng số dư tín dụng để thanh toán', 'jankx')}</span>
                    </label>
                    <p className="jankx-credits-balance">
                        {__('Số dư tín dụng:', 'jankx')} <strong className="jankx-credits-balance-value">0₫</strong>
                    </p>
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