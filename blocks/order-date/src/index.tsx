import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-field jankx-order-field--date jankx-order-field-editor',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-order-field__label">Ngày đặt hàng:</span>
            <span className="jankx-order-field__value">10:00 16/10/2026</span>
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
