import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, SelectControl } from '@wordpress/components';
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
                <PanelBody title={__('Cài đặt hiển thị giá', 'jankx')}>
                    <TextControl
                        label={__('Tiền tố', 'jankx')}
                        value={attributes.prefix}
                        onChange={(value) => setAttributes({ prefix: value })}
                    />
                    <TextControl
                        label={__('Hậu tố', 'jankx')}
                        value={attributes.suffix}
                        onChange={(value) => setAttributes({ suffix: value })}
                    />
                    <ToggleControl
                        label={__('Hiển thị khi chưa có giá', 'jankx')}
                        checked={attributes.showWhenEmpty}
                        onChange={(value) => setAttributes({ showWhenEmpty: value })}
                    />
                    <TextControl
                        label={__('Chữ khi chưa có giá', 'jankx')}
                        value={attributes.emptyText}
                        onChange={(value) => setAttributes({ emptyText: value })}
                    />
                    <SelectControl
                        label={__('Thẻ HTML', 'jankx')}
                        value={attributes.tagName}
                        options={[
                            { label: 'span', value: 'span' },
                            { label: 'div', value: 'div' },
                            { label: 'p', value: 'p' },
                            { label: 'strong', value: 'strong' },
                        ]}
                        onChange={(value) => setAttributes({ tagName: value })}
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