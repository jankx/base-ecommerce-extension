import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps } from '@wordpress/blocks';
import metadata from '../block.json';

interface CurrencySwitcherAttributes {
    displayMode: string;
    showFlag: boolean;
    showCode: boolean;
    showSymbol: boolean;
    showName: boolean;
    layout: string;
    iconSize: string;
}

export default function Edit({ attributes, setAttributes }: BlockEditProps<CurrencySwitcherAttributes>) {
    const blockProps = useBlockProps({ className: 'jankx-server-rendered' });
    const { displayMode, showFlag, showCode, showSymbol, showName, layout, iconSize } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Currency Switcher Settings', 'jankx')}>
                    <SelectControl
                        label={__('Display Mode', 'jankx')}
                        value={displayMode}
                        options={[
                            { label: __('Dropdown', 'jankx'), value: 'dropdown' },
                            { label: __('Inline List', 'jankx'), value: 'inline' },
                            { label: __('Button Group', 'jankx'), value: 'buttons' },
                        ]}
                        onChange={(val: string) => setAttributes({ displayMode: val })}
                    />
                    <SelectControl
                        label={__('Layout', 'jankx')}
                        value={layout}
                        options={[
                            { label: __('Horizontal', 'jankx'), value: 'horizontal' },
                            { label: __('Vertical', 'jankx'), value: 'vertical' },
                        ]}
                        onChange={(val: string) => setAttributes({ layout: val })}
                    />
                    <SelectControl
                        label={__('Icon Size', 'jankx')}
                        value={iconSize}
                        options={[
                            { label: __('Small', 'jankx'), value: 'small' },
                            { label: __('Medium', 'jankx'), value: 'medium' },
                            { label: __('Large', 'jankx'), value: 'large' },
                        ]}
                        onChange={(val: string) => setAttributes({ iconSize: val })}
                    />
                    <ToggleControl
                        label={__('Show Flag', 'jankx')}
                        checked={showFlag}
                        onChange={(val: boolean) => setAttributes({ showFlag: val })}
                    />
                    <ToggleControl
                        label={__('Show Code', 'jankx')}
                        checked={showCode}
                        onChange={(val: boolean) => setAttributes({ showCode: val })}
                    />
                    <ToggleControl
                        label={__('Show Symbol', 'jankx')}
                        checked={showSymbol}
                        onChange={(val: boolean) => setAttributes({ showSymbol: val })}
                    />
                    <ToggleControl
                        label={__('Show Name', 'jankx')}
                        checked={showName}
                        onChange={(val: boolean) => setAttributes({ showName: val })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}
