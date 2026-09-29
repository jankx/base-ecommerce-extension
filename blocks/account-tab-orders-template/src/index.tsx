import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

const ITEM_ALLOWED_BLOCKS = [
    'jankx/order-number',
    'jankx/order-status',
    'jankx/order-date',
    'jankx/order-total',
    'jankx/order-cancel',
];

const DEFAULT_TEMPLATE = ITEM_ALLOWED_BLOCKS.map((name) => [name]);

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-card jankx-order-card--template',
    });

    return (
        <div {...blockProps}>
            <div className="jankx-order-card--template-note">
                Mẫu cho mỗi đơn hàng trong danh sách — thêm/bớt các block thành phần bên dưới.
            </div>
            <InnerBlocks
                allowedBlocks={ITEM_ALLOWED_BLOCKS}
                template={DEFAULT_TEMPLATE}
                templateLock={false}
            />
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save({
        className: 'jankx-order-card jankx-order-card--template',
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
