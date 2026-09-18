import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const SECTION_BLOCKS = [
    'jankx/checkout-customer-details',
    'jankx/checkout-payment-methods',
    'jankx/checkout-order-review',
    'jankx/checkout-credits',
    'jankx/checkout-actions',
];

const ALLOWED_BLOCKS = [
    ...SECTION_BLOCKS,
    'jankx/checkout-empty',
];

const DEFAULT_TEMPLATE = [
    ['jankx/checkout-customer-details', { jankxCheckoutColumn: 'customer' }],
    ['jankx/checkout-payment-methods', { jankxCheckoutColumn: 'customer' }],
    ['jankx/checkout-order-review', { jankxCheckoutColumn: 'summary' }],
    ['jankx/checkout-credits', { jankxCheckoutColumn: 'summary' }],
    ['jankx/checkout-actions', { jankxCheckoutColumn: 'summary' }],
    ['jankx/checkout-empty'],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-checkout-block',
    });

    return (
        <div {...blockProps}>
            <div className="jankx-checkout-block__editor-note">
                {__('Tùy chỉnh thanh toán: Section có cột "customer" hiển thị bên trái, cột "summary" hiển thị bên phải. Block checkout-empty hiển thị khi giỏ hàng trống.', 'jankx')}
            </div>
            <div className="jankx-checkout-block__sections-preview">
                <InnerBlocks
                    allowedBlocks={ALLOWED_BLOCKS}
                    template={DEFAULT_TEMPLATE}
                    templateLock={false}
                />
            </div>
        </div>
    );
}

function Save({ attributes }) {
    const blockProps = useBlockProps.save({
        className: 'jankx-checkout-block',
    });

    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});