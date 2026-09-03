import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
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
                <PanelBody title={__('Currency Switcher Settings', 'jankx')}>
                    <SelectControl
                        label={__('Display Mode', 'jankx')}
                        value={attributes.displayMode}
                        options={[
                            { label: __('Dropdown', 'jankx'), value: 'dropdown' },
                            { label: __('Inline List', 'jankx'), value: 'list' }
                        ]}
                        onChange={(value) => setAttributes({ displayMode: value })}
                    />
                    <SelectControl
                        label={__('Layout', 'jankx')}
                        value={attributes.layout}
                        options={[
                            { label: __('Horizontal', 'jankx'), value: 'horizontal' },
                            { label: __('Vertical', 'jankx'), value: 'vertical' }
                        ]}
                        onChange={(value) => setAttributes({ layout: value })}
                    />
                    <SelectControl
                        label={__('Icon Size', 'jankx')}
                        value={attributes.iconSize}
                        options={[
                            { label: __('Small', 'jankx'), value: 'small' },
                            { label: __('Medium', 'jankx'), value: 'medium' },
                            { label: __('Large', 'jankx'), value: 'large' }
                        ]}
                        onChange={(value) => setAttributes({ iconSize: value })}
                    />
                    <ToggleControl
                        label={__('Show Flag', 'jankx')}
                        checked={attributes.showFlag}
                        onChange={(value) => setAttributes({ showFlag: value })}
                    />
                    <ToggleControl
                        label={__('Show Code', 'jankx')}
                        checked={attributes.showCode}
                        onChange={(value) => setAttributes({ showCode: value })}
                    />
                    <ToggleControl
                        label={__('Show Symbol', 'jankx')}
                        checked={attributes.showSymbol}
                        onChange={(value) => setAttributes({ showSymbol: value })}
                    />
                    <ToggleControl
                        label={__('Show Name', 'jankx')}
                        checked={attributes.showName}
                        onChange={(value) => setAttributes({ showName: value })}
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
