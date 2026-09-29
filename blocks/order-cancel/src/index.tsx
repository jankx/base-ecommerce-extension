import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-field jankx-order-field--cancel jankx-order-field-editor',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-btn jankx-btn-outline jankx-order-cancel">Hủy đơn hàng</span>
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
