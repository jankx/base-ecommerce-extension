import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-cart-item-editor jankx-cart-item-editor--price',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-cart-item-editor__label">1.000.000đ</span>
        </div>
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