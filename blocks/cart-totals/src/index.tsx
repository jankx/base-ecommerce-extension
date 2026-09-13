import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-cart-totals jankx-cart-totals--editor',
    });

    return (
        <div {...blockProps}>
            <h2 className="jankx-section-title">
                {__('Cart totals', 'jankx')}
            </h2>

            <div className="jankx-coupon-form">
                <div className="jankx-coupon-input-row">
                    <input
                        type="text"
                        className="jankx-coupon-code"
                        placeholder={__('Nhập mã giảm giá', 'jankx')}
                        disabled
                    />
                    <button
                        type="button"
                        className="jankx-btn jankx-btn-primary jankx-coupon-apply"
                        disabled
                    >
                        {__('Áp dụng', 'jankx')}
                    </button>
                </div>
            </div>

            <div className="jankx-total-row">
                <span>{__('Subtotal', 'jankx')}</span>
                <span>{__('1.500.000₫', 'jankx')}</span>
            </div>

            <div className="jankx-total-row jankx-total-grand">
                <span>{__('Total', 'jankx')}</span>
                <span>{__('1.500.000₫', 'jankx')}</span>
            </div>

            <div className="jankx-cart-actions">
                <button
                    type="button"
                    className="jankx-btn jankx-btn-primary jankx-btn-checkout"
                    disabled
                >
                    {__('Proceed to checkout', 'jankx')}
                </button>
            </div>
        </div>
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
