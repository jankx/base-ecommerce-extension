import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';
import './style.scss';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'jankx-server-rendered',
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Currency Switcher Settings', 'base-ecommerce')}>
                    <SelectControl
                        label={__('Display Mode', 'base-ecommerce')}
                        value={attributes.displayMode}
                        options={[
                            { label: __('Dropdown', 'base-ecommerce'), value: 'dropdown' },
                            { label: __('Inline List', 'base-ecommerce'), value: 'list' }
                        ]}
                        onChange={(value) => setAttributes({ displayMode: value })}
                    />
                    <SelectControl
                        label={__('Layout', 'base-ecommerce')}
                        value={attributes.layout}
                        options={[
                            { label: __('Horizontal', 'base-ecommerce'), value: 'horizontal' },
                            { label: __('Vertical', 'base-ecommerce'), value: 'vertical' }
                        ]}
                        onChange={(value) => setAttributes({ layout: value })}
                    />
                    <SelectControl
                        label={__('Icon Size', 'base-ecommerce')}
                        value={attributes.iconSize}
                        options={[
                            { label: __('Small', 'base-ecommerce'), value: 'small' },
                            { label: __('Medium', 'base-ecommerce'), value: 'medium' },
                            { label: __('Large', 'base-ecommerce'), value: 'large' }
                        ]}
                        onChange={(value) => setAttributes({ iconSize: value })}
                    />
                    <ToggleControl
                        label={__('Show Flag', 'base-ecommerce')}
                        checked={attributes.showFlag}
                        onChange={(value) => setAttributes({ showFlag: value })}
                    />
                    <ToggleControl
                        label={__('Show Code', 'base-ecommerce')}
                        checked={attributes.showCode}
                        onChange={(value) => setAttributes({ showCode: value })}
                    />
                    <ToggleControl
                        label={__('Show Symbol', 'base-ecommerce')}
                        checked={attributes.showSymbol}
                        onChange={(value) => setAttributes({ showSymbol: value })}
                    />
                    <ToggleControl
                        label={__('Show Name', 'base-ecommerce')}
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
