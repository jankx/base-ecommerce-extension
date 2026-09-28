import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './style.scss';

const STEP_BLOCK = 'jankx/order-progress-step';

const DEFAULT_TEMPLATE = [
    [STEP_BLOCK, { label: __('Placed', 'base-ecommerce'), icon: 'check-circle' }],
    [STEP_BLOCK, { label: __('Processing', 'base-ecommerce'), icon: 'spinner' }],
    [STEP_BLOCK, { label: __('Shipping', 'base-ecommerce'), icon: 'truck' }],
    [STEP_BLOCK, { label: __('Completed', 'base-ecommerce'), icon: 'check-circle' }],
];

function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-od-progress',
    });

    return (
        <div {...blockProps}>
            <InnerBlocks
                allowedBlocks={[STEP_BLOCK]}
                template={DEFAULT_TEMPLATE}
                templateLock={false}
            />
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save({
        className: 'jankx-od-progress',
    });

    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: Save,
});