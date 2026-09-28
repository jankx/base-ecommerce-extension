import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './style.scss';

const ICONS = {
    'none': null,
    'check-circle': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
    ),
    'clock': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
    ),
    'spinner': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
        </svg>
    ),
    'truck': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <rect x="1" y="3" width="15" height="13" />
            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
            <circle cx="5.5" cy="18.5" r="2.5" />
            <circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
    ),
    'x-circle': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
        </svg>
    ),
    'rotate-ccw': (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <polyline points="1 4 1 10 7 10" />
            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
        </svg>
    ),
};

function Edit({ attributes, setAttributes }) {
    const { label, state, icon } = attributes;

    const classes = state === 'todo'
        ? 'jankx-od-step'
        : state === 'terminal'
            ? 'jankx-od-step jankx-od-step--terminal jankx-od-step--done'
            : `jankx-od-step jankx-od-step--${state}`;

    const blockProps = useBlockProps({ className: classes });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Settings', 'base-ecommerce')}>
                    <SelectControl
                        label={__('State', 'base-ecommerce')}
                        value={state}
                        onChange={(value) => setAttributes({ state: value })}
                        options={[
                            { value: 'todo', label: __('To do', 'base-ecommerce') },
                            { value: 'active', label: __('Active', 'base-ecommerce') },
                            { value: 'done', label: __('Completed', 'base-ecommerce') },
                            { value: 'terminal', label: __('Failed / Cancelled', 'base-ecommerce') },
                        ]}
                    />
                    <SelectControl
                        label={__('Icon', 'base-ecommerce')}
                        value={icon}
                        onChange={(value) => setAttributes({ icon: value })}
                        options={[
                            { value: 'none', label: __('None', 'base-ecommerce') },
                            { value: 'check-circle', label: __('Check circle', 'base-ecommerce') },
                            { value: 'clock', label: __('Clock', 'base-ecommerce') },
                            { value: 'spinner', label: __('Spinner', 'base-ecommerce') },
                            { value: 'truck', label: __('Truck', 'base-ecommerce') },
                            { value: 'x-circle', label: __('X circle', 'base-ecommerce') },
                            { value: 'rotate-ccw', label: __('Undo', 'base-ecommerce') },
                        ]}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <span className="jankx-od-step-dot">{ICONS[icon]}</span>
                <RichText
                    tagName="span"
                    className="jankx-od-step-label"
                    value={label}
                    onChange={(value) => setAttributes({ label: value })}
                    placeholder={__('Step label', 'base-ecommerce')}
                />
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