import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'jankx-server-rendered',
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt nút Thêm vào giỏ hàng', 'jankx')}>
                    <TextControl
                        label={__('Tiêu đề', 'jankx')}
                        value={attributes.title}
                        onChange={(value) => setAttributes({ title: value })}
                    />
                    <ToggleControl
                        label={__('Hiển thị ô số lượng', 'jankx')}
                        checked={attributes.show_quantity}
                        onChange={(value) => setAttributes({ show_quantity: value })}
                    />
                    <ToggleControl
                        label={__('Hiển thị ngày khởi hành', 'jankx')}
                        checked={attributes.show_departure}
                        onChange={(value) => setAttributes({ show_departure: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
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
