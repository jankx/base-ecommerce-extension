import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

function Edit({ attributes, setAttributes }) {
    const { title, description, buttonText, buttonUrl } = attributes;
    const blockProps = useBlockProps({
        className: 'jankx-empty-state jankx-cart-empty--editor',
    });

    const displayTitle = title || __('Your cart is empty', 'jankx');
    const displayDesc = description || __('Add some products before checking out.', 'jankx');
    const displayBtn = buttonText || __('Continue shopping', 'jankx');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Settings', 'jankx')} initialOpen={true}>
                    <TextControl
                        label={__('Tiêu đề', 'jankx')}
                        value={title}
                        onChange={(val) => setAttributes({ title: val })}
                        placeholder={__('Your cart is empty', 'jankx')}
                    />
                    <TextControl
                        label={__('Mô tả', 'jankx')}
                        value={description}
                        onChange={(val) => setAttributes({ description: val })}
                        placeholder={__('Add some products before checking out.', 'jankx')}
                    />
                    <TextControl
                        label={__('Chữ trên nút', 'jankx')}
                        value={buttonText}
                        onChange={(val) => setAttributes({ buttonText: val })}
                        placeholder={__('Continue shopping', 'jankx')}
                    />
                    <TextControl
                        label={__('Đường dẫn nút (URL)', 'jankx')}
                        value={buttonUrl}
                        onChange={(val) => setAttributes({ buttonUrl: val })}
                        placeholder={__('Mặc định: Trang chủ', 'jankx')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div style={{
                    display: 'inline-block',
                    padding: '2px 8px',
                    marginBottom: '8px',
                    fontSize: '11px',
                    fontWeight: 600,
                    textTransform: 'uppercase',
                    background: '#e3f2fd',
                    color: '#1565c0',
                    borderRadius: '4px',
                }}>
                    {__('Hiển thị khi giỏ hàng trống', 'jankx')}
                </div>
                <span className="jankx-empty-icon" aria-hidden="true">&#128722;</span>
                <RichText
                    tagName="h2"
                    className="jankx-section-title"
                    value={title}
                    onChange={(val) => setAttributes({ title: val })}
                    placeholder={__('Your cart is empty', 'jankx')}
                />
                <RichText
                    tagName="p"
                    value={description}
                    onChange={(val) => setAttributes({ description: val })}
                    placeholder={__('Add some products before checking out.', 'jankx')}
                />
                <div style={{ marginTop: '1rem' }}>
                    <span className="jankx-btn jankx-btn-primary">
                        {displayBtn}
                    </span>
                </div>
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
