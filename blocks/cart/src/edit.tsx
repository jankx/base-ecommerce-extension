import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps } from '@wordpress/blocks';
import metadata from '../block.json';

export default function Edit(_props: BlockEditProps<{}>) {
    const blockProps = useBlockProps({ className: 'jankx-server-rendered' });

    return (
        <div {...blockProps}>
            <ServerSideRender block={metadata.name} />
        </div>
    );
}
