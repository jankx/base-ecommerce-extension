import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const DEFAULT_TEMPLATE = [
    [
        'core/paragraph',
        {
            align: 'center',
            content: '📦',
            fontSize: 'large',
            className: 'jankx-empty-icon',
        },
    ],
    [
        'core/heading',
        {
            textAlign: 'center',
            level: 2,
            content: __('You have no orders yet.', 'base-ecommerce'),
            className: 'jankx-section-title',
        },
    ],
    [
        'core/paragraph',
        {
            align: 'center',
            content: __('Browse our tours to place your first order.', 'base-ecommerce'),
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
                    text: __('Explore tours', 'base-ecommerce'),
                    url: '/',
                    className: 'jankx-btn jankx-btn-primary',
                },
            ],
        ],
    ],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-empty-state jankx-tab-orders-empty',
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
                {__('Chỉ hiển thị khi chưa có đơn hàng (No Orders)', 'base-ecommerce')}
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
