/**
 * E-Commerce Base Blocks - Editor integration
 *
 * Registers the server-rendered ecommerce blocks (cart, checkout,
 * account-tab-orders) client-side in the block editor. Metadata is passed
 * from PHP via wp_localize_script. Each block renders its live preview
 * through ServerSideRender and saves nothing (dynamic blocks).
 */
(function () {
  'use strict';

  var registerBlockType = wp.blocks.registerBlockType;
  var addFilter = wp.hooks.addFilter;
  var SSR = wp.serverSideRender;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var select = wp.data.select;
  var el = wp.element.createElement;

  var blockMetadata = window.jankxEcommerceBlockMetadata || {};

  Object.keys(blockMetadata).forEach(function (blockName) {
    // Skip blocks that have their own editorScript – those register themselves.
    if (blockMetadata[blockName] && blockMetadata[blockName].editorScript) {
      return;
    }
    if (select('core/blocks').getBlockType(blockName)) {
      return;
    }
    registerBlockType(blockName, blockMetadata[blockName]);
  });

  addFilter(
    'blocks.registerBlockType',
    'jankx/ecommerce-editor-inject',
    function (settings, name) {
      var meta = blockMetadata[name];
      if (!meta) {
        return settings;
      }

      // Blocks with a dedicated editorScript already have their own full edit
      // function (including InspectorControls). Do not overwrite it here.
      if (meta.editorScript) {
        return settings;
      }

      settings.edit = function (props) {
        var blockProps = useBlockProps({ className: 'jankx-server-rendered' });
        return el('div', blockProps,
          el(SSR, { block: name, attributes: props.attributes })
        );
      };
      settings.save = function () { return null; };

      return settings;
    }
  );
})();
