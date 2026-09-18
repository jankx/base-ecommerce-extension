import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const ITEM_ALLOWED_BLOCKS = [
    'jankx/checkout-review-item-name',
    'jankx/checkout-review-item-price',
];

const ITEM_TEMPLATE = ITEM_ALLOWED_BLOCKS.map((name) => [name]);

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
                <h2 className="jankx-section-title">{__('Your order', 'jankx')}</h2>
                <div className="jankx-order-review">
                    <div className="jankx-review-item">
                        <InnerBlocks
                            allowedBlocks={ITEM_ALLOWED_BLOCKS}
                            template={ITEM_TEMPLATE}
                            templateLock={false}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

function Save() {
    return <InnerBlocks.Content />;
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});