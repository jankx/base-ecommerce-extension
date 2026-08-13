import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps } from '@wordpress/blocks';
import metadata from '../block.json';

interface AddToCartAttributes {
    title: string;
    show_quantity: boolean;
    show_departure: boolean;
    jankxHideOnUltrawide: boolean;
    jankxHideOnPc: boolean;
    jankxHideOnTablet: boolean;
    jankxHideOnMobile: boolean;
    jankxFlexGrow: boolean;
}

export default function Edit({ attributes, setAttributes }: BlockEditProps<AddToCartAttributes>) {
    const blockProps = useBlockProps({ className: 'jankx-server-rendered' });
    const { title, show_quantity, show_departure } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Add to Cart Settings', 'jankx')}>
                    <TextControl
                        label={__('Button Label', 'jankx')}
                        value={title}
                        onChange={(val: string) => setAttributes({ title: val })}
                    />
                    <ToggleControl
                        label={__('Show Quantity Input', 'jankx')}
                        checked={show_quantity}
                        onChange={(val: boolean) => setAttributes({ show_quantity: val })}
                    />
                    <ToggleControl
                        label={__('Show Departure Date', 'jankx')}
                        checked={show_departure}
                        onChange={(val: boolean) => setAttributes({ show_departure: val })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}
