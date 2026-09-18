import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const DEFAULT_TEMPLATE = [
    [
        'core/paragraph',
        {
            align: 'center',
            content: '🛒',
            fontSize: 'large',
            className: 'jankx-empty-icon',
        },
    ],
    [
        'core/heading',
        {
            textAlign: 'center',
            level: 2,
            content: __('Nothing to check out yet', 'jankx'),
            className: 'jankx-section-title',
        },
    ],
    [
        'core/paragraph',
        {
            align: 'center',
            content: __('Add some products to your cart before checking out.', 'jankx'),
        },
    ],
    [
        'core/buttons',
        {
            layout: { type: 'flex', justifyCenter: true },
        },
        [
            [
                'core/button',
                {
                    text: __('Continue shopping', 'jankx'),
                    url: '/',
                    className: 'jankx-btn jankx-btn-primary',
                },
            ],
        ],
    ],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-empty-state jankx-checkout-empty--editor',
    });

    return (
        <div {...blockProps}>
            <div style={{
                display: 'inline-block',
                padding: '2px 8px',
                marginBottom: '12px',
                fontSize: '11px',
                fontWeight: 600,
                textTransform: 'uppercase',
                background: '#e3f2fd',
                color: '#1565c0',
                borderRadius: '4px',
            }}>
                {__('Hiển thị khi giỏ hàng trống (Empty Checkout)', 'jankx')}
            </div>
            <InnerBlocks
                template={DEFAULT_TEMPLATE}
                templateLock={false}
            />
        </div>
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