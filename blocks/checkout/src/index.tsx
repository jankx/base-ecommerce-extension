import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';

function Edit({ attributes }) {
    const blockProps = useBlockProps({
        className: 'jankx-server-rendered',
    });

    return (
        <div {...blockProps}>
            <ServerSideRender block={metadata.name} attributes={attributes} />
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
