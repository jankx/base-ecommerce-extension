import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

const ITEM_ALLOWED_BLOCKS = [
    'jankx/cart-item-checkbox',
    'jankx/cart-item-image',
    'jankx/cart-item-title',
    'jankx/cart-item-meta',
    'jankx/cart-item-quantity',
    'jankx/cart-item-price',
    'jankx/cart-item-remove',
];

const ALLOWED_BLOCKS = [
    ...ITEM_ALLOWED_BLOCKS,
    'jankx/cart-totals',
    'jankx/cart-empty',
];

const DEFAULT_TEMPLATE = [
    ...ITEM_ALLOWED_BLOCKS.map((name) => [name]),
    ['jankx/cart-totals'],
    ['jankx/cart-empty'],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-cart-block',
    });

    return (
        <div {...blockProps}>
            <div className="jankx-cart-block__editor-note">
                Tùy chỉnh giỏ hàng: Các block item và totals hiển thị khi có sản phẩm; block cart-empty hiển thị khi giỏ hàng trống.
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