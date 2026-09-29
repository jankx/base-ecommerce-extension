import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../block.json';

const TABS = ['Tất cả', 'Chờ thanh toán', 'Đã hoàn thành', 'Đã hủy'];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-order-filters',
    });
    return (
        <div {...blockProps}>
            {TABS.map((label, i) => (
                <span
                    key={label}
                    className={'jankx-order-filter' + (i === 0 ? ' is-active' : '')}
                >
                    {label}
                </span>
            ))}
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
