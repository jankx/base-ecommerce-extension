import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

const ALLOWED_BLOCKS = [
    'jankx/account-tab-orders-filters',
    'jankx/account-tab-orders-search',
    'jankx/account-tab-orders-template',
    'jankx/account-tab-orders-empty',
];

const DEFAULT_TEMPLATE = [
    ['jankx/account-tab-orders-filters'],
    ['jankx/account-tab-orders-search'],
    ['jankx/account-tab-orders-template'],
    ['jankx/account-tab-orders-empty'],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-tab-orders',
    });

    return (
        <div {...blockProps}>
            <div className="jankx-tab-orders__editor-note">
                Danh sách đơn hàng: filters + tìm kiếm hiển thị phía trên; block template lặp lại cho
                từng đơn hàng (kéo thả các block thành phần để tuỳ chỉnh layout). Block empty chỉ hiển
                thị khi khách chưa có đơn nào.
            </div>
            <InnerBlocks
                allowedBlocks={ALLOWED_BLOCKS}
                template={DEFAULT_TEMPLATE}
                templateLock={false}
            />
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save({
        className: 'jankx-tab-orders',
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
