import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const ALLOWED_BLOCKS = [
    'jankx/svg-icon',
    'jankx/icon-picker',
    'core/image',
];

const CART_SVG = '<svg width="24" height="40" viewBox="0 0 24 40" fill="none" xmlns="http://www.w3.org/2000/svg">'
    + '<path d="M8 24L16.7201 23.2733C19.4486 23.046 20.0611 22.45 20.3635 19.7289L21 14" stroke="#5C6C7E" stroke-width="1.5" stroke-linecap="round"/>'
    + '<path d="M6 14H22" stroke="#5C6C7E" stroke-width="1.5" stroke-linecap="round"/>'
    + '<circle cx="6" cy="28" r="2" stroke="#5C6C7E" stroke-width="1.5"/>'
    + '<circle cx="17" cy="28" r="2" stroke="#5C6C7E" stroke-width="1.5"/>'
    + '<path d="M8 28L15 28" stroke="#5C6C7E" stroke-width="1.5" stroke-linecap="round"/>'
    + '<path d="M2 10H2.966C3.91068 10 4.73414 10.6246 4.96326 11.5149L7.93852 23.0765C8.08887 23.6608 7.9602 24.2797 7.58824 24.7616L6.63213 26" stroke="#5C6C7E" stroke-width="1.5" stroke-linecap="round"/>'
    + '</svg>';

const EDIT_TEMPLATE = [
    ['jankx/svg-icon', { icon: CART_SVG, width: '24px' }],
];

function Edit({ attributes, setAttributes, clientId }) {
    const blockProps = useBlockProps({
        className: 'jankx-mini-cart',
    });

    const hasInnerBlocks = useSelect(
        (select) => (select('core/block-editor') as any).getBlockCount(clientId) > 0,
        [clientId]
    );

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

                <PanelColorSettings
                    title={__('Badge Colors', 'jankx')}
                    colorSettings={[
                        {
                            value: attributes.badgeColor,
                            onChange: (colorValue) => setAttributes({ badgeColor: colorValue }),
                            label: __('Text Color', 'jankx'),
                        },
                        {
                            value: attributes.badgeBgColor,
                            onChange: (colorValue) => setAttributes({ badgeBgColor: colorValue }),
                            label: __('Background Color', 'jankx'),
                        },
                        {
                            value: attributes.badgeBorderColor,
                            onChange: (colorValue) => setAttributes({ badgeBorderColor: colorValue }),
                            label: __('Border Color', 'jankx'),
                        },
                    ]}
                />

                <PanelBody title={__('Badge Styles', 'jankx')} initialOpen={false}>
                    <TextControl
                        label={__('Top Position', 'jankx')}
                        value={attributes.badgeTop || ''}
                        onChange={(val) => setAttributes({ badgeTop: val })}
                        help="e.g. -4px or 0"
                    />
                    <TextControl
                        label={__('Right Position', 'jankx')}
                        value={attributes.badgeRight || ''}
                        onChange={(val) => setAttributes({ badgeRight: val })}
                        help="e.g. -4px or 0"
                    />
                    <TextControl
                        label={__('Width', 'jankx')}
                        value={attributes.badgeWidth || ''}
                        onChange={(val) => setAttributes({ badgeWidth: val })}
                        help="e.g. 18px"
                    />
                    <TextControl
                        label={__('Height', 'jankx')}
                        value={attributes.badgeHeight || ''}
                        onChange={(val) => setAttributes({ badgeHeight: val })}
                        help="e.g. 18px"
                    />
                    <TextControl
                        label={__('Font Size', 'jankx')}
                        value={attributes.badgeFontSize || ''}
                        onChange={(val) => setAttributes({ badgeFontSize: val })}
                        help="e.g. 11px or 0.8rem"
                    />
                    <TextControl
                        label={__('Border Width', 'jankx')}
                        value={attributes.badgeBorderWidth || ''}
                        onChange={(val) => setAttributes({ badgeBorderWidth: val })}
                        help="e.g. 1px"
                    />
                    <TextControl
                        label={__('Border Radius', 'jankx')}
                        value={attributes.badgeBorderRadius || ''}
                        onChange={(val) => setAttributes({ badgeBorderRadius: val })}
                        help="e.g. 99px"
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <button type="button" className="jankx-mini-cart-toggle" aria-expanded="false"
                    aria-label={__('Open cart', 'jankx')} style={{ pointerEvents: 'none' }}>
                    <span className="jankx-mini-cart-icon" aria-hidden="true">
                        {!hasInnerBlocks && (
                            <svg width="24" height="40" viewBox="0 0 24 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 24L16.7201 23.2733C19.4486 23.046 20.0611 22.45 20.3635 19.7289L21 14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                <path d="M6 14H22" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                <circle cx="6" cy="28" r="2" stroke="currentColor" strokeWidth="1.5" />
                                <circle cx="17" cy="28" r="2" stroke="currentColor" strokeWidth="1.5" />
                                <path d="M8 28L15 28" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                <path d="M2 10H2.966C3.91068 10 4.73414 10.6246 4.96326 11.5149L7.93852 23.0765C8.08887 23.6608 7.9602 24.2797 7.58824 24.7616L6.63213 26" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                            </svg>
                        )}
                        <InnerBlocks
                            allowedBlocks={ALLOWED_BLOCKS}
                            template={EDIT_TEMPLATE}
                            templateLock="all"
                            renderAppender={false}
                        />
                    </span>
                    <span
                        className="jankx-mini-cart-count"
                        data-jankx-cart-count
                        style={{
                            color: attributes.badgeColor,
                            backgroundColor: attributes.badgeBgColor,
                            top: attributes.badgeTop,
                            right: attributes.badgeRight,
                            minWidth: attributes.badgeWidth,
                            height: attributes.badgeHeight,
                            lineHeight: attributes.badgeHeight,
                            fontSize: attributes.badgeFontSize,
                            borderWidth: attributes.badgeBorderWidth,
                            borderColor: attributes.badgeBorderColor,
                            borderStyle: attributes.badgeBorderWidth ? 'solid' : undefined,
                            borderRadius: attributes.badgeBorderRadius,
                            // Quick manual padding representation for editor inline styles (if string)
                            padding: typeof attributes.badgePadding === 'string' ? attributes.badgePadding : undefined,
                            margin: typeof attributes.badgeMargin === 'string' ? attributes.badgeMargin : undefined,
                        }}
                    >
                        3
                    </span>
                </button>
            </div>
        </>
    );
}

function Save() {
    return <InnerBlocks.Content />;
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});
