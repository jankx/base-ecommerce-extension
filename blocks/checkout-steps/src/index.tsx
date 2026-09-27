import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

function Edit({ attributes, setAttributes }) {
    const { currentStep } = attributes;
    const blockProps = useBlockProps({
        className: 'jankx-checkout-steps',
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Settings', 'base-ecommerce')}>
                    <RangeControl
                        label={__('Current Step', 'base-ecommerce')}
                        value={currentStep}
                        onChange={(value) => setAttributes({ currentStep: value })}
                        min={1}
                        max={3}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="jankx-steps-container">
                    <div className={`jankx-step ${currentStep >= 1 ? 'is-active' : ''} ${currentStep > 1 ? 'is-completed' : ''}`}>
                        <div className="jankx-step-icon">
                            {currentStep > 1 ? (
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            ) : 1}
                        </div>
                        <div className="jankx-step-label">{__('Chọn đơn hàng', 'base-ecommerce')}</div>
                    </div>
                    <div className={`jankx-step-line ${currentStep >= 2 ? 'is-active' : ''}`}></div>
                    <div className={`jankx-step ${currentStep >= 2 ? 'is-active' : ''} ${currentStep > 2 ? 'is-completed' : ''}`}>
                        <div className="jankx-step-icon">
                            {currentStep > 2 ? (
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            ) : (currentStep === 2 ? <span className="jankx-dots">...</span> : 2)}
                        </div>
                        <div className="jankx-step-label">{__('Điền thông tin', 'base-ecommerce')}</div>
                    </div>
                    <div className={`jankx-step-line ${currentStep >= 3 ? 'is-active' : ''}`}></div>
                    <div className={`jankx-step ${currentStep >= 3 ? 'is-active' : ''}`}>
                        <div className="jankx-step-icon">3</div>
                        <div className="jankx-step-label">{__('Thanh toán', 'base-ecommerce')}</div>
                    </div>
                </div>
            </div>
        </>
    );
}

function Save() {
    return null; // Rendered via PHP
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});
