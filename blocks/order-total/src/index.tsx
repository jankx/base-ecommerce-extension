import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-field jankx-order-field--total jankx-order-field-editor',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-order-field__label">Tổng tiền:</span>
            <span className="jankx-order-field__value">1.100.000đ</span>
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
