import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';

const ALLOWED_BLOCKS = [
    'jankx/svg-icon',
    'jankx/icon-picker',
    'core/image',
];

const EDIT_TEMPLATE = [
    ['jankx/svg-icon', {}],
];

function Edit({ attributes, setAttributes, clientId }) {
    const blockProps = useBlockProps({
        className: 'jankx-server-rendered',
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Mini Cart Settings', 'jankx')}>
                    <ToggleControl
                        label={__('Hide on Desktop', 'jankx')}
                        checked={attributes.jankxHideOnPc}
                        onChange={(val) => setAttributes({ jankxHideOnPc: val })}
                    />
                    <ToggleControl
                        label={__('Hide on Tablet', 'jankx')}
                        checked={attributes.jankxHideOnTablet}
                        onChange={(val) => setAttributes({ jankxHideOnTablet: val })}
                    />
                    <ToggleControl
                        label={__('Hide on Mobile', 'jankx')}
                        checked={attributes.jankxHideOnMobile}
                        onChange={(val) => setAttributes({ jankxHideOnMobile: val })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="jankx-mini-cart-editor-icon">
                    <InnerBlocks
                        allowedBlocks={ALLOWED_BLOCKS}
                        template={EDIT_TEMPLATE}
                        templateLock={false}
                        renderAppender={InnerBlocks.ButtonBlockAppender}
                    />
                </div>
                <ServerSideRender
                    block={metadata.name}
                    attributes={attributes}
                />
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
