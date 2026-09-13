import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-cart-item-editor jankx-cart-item-editor--meta',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-cart-item-editor__label">Loại tour · Ngày khởi hành · Hành khách</span>
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