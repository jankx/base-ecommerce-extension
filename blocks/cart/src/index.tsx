import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

const ALLOWED_BLOCKS = [
    'jankx/cart-item-checkbox',
    'jankx/cart-item-image',
    'jankx/cart-item-title',
    'jankx/cart-item-meta',
    'jankx/cart-item-quantity',
    'jankx/cart-item-price',
    'jankx/cart-item-remove',
];

const DEFAULT_TEMPLATE = ALLOWED_BLOCKS.map((name) => [name]);

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-cart-block',
    });

    return (
        <div {...blockProps}>
            <div className="jankx-cart-block__editor-note">
                Sắp xếp các block con để tạo layout cho mỗi sản phẩm trong giỏ hàng.
            </div>
            <div className="jankx-cart-block__items-preview">
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
        className: 'jankx-cart-block',
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