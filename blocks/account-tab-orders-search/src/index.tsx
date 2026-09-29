import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-search',
    });
    return (
        <div {...blockProps}>
            <span className="jankx-order-search__input">Tìm kiếm đơn hàng</span>
            <span className="jankx-order-search__date">Từ ngày</span>
            <span className="jankx-order-search__date">Đến ngày</span>
            <span className="jankx-btn jankx-btn-primary jankx-order-search__submit">Tìm kiếm</span>
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
