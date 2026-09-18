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
                <div className="jankx-payment-methods">
                    <label className="jankx-payment-method">
                        <input type="radio" name="payment_method" value="bank_transfer" defaultChecked disabled />
                        <span>{__('Chuyển khoản ngân hàng', 'jankx')}</span>
                    </label>
                    <label className="jankx-payment-method">
                        <input type="radio" name="payment_method" value="cod" disabled />
                        <span>{__('Thanh toán khi nhận hàng (COD)', 'jankx')}</span>
                    </label>
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