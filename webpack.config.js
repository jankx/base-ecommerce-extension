/**
 * webpack.config.js – base-ecommerce extension
 *
 * Extends the default @wordpress/scripts webpack config to build all
 * Gutenberg blocks inside this extension. Each block gets its own
 * output chunk so WordPress can `register_block_type()` them individually.
 *
 * Build:  wp-scripts build --webpack-src-dir=. --config=webpack.config.js
 *   (or use the npm scripts defined in the root theme's package.json)
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,

    /**
     * Multiple entry points — one per block.
     * Output will be placed in each block's own `build/` directory via
     * the custom `output.filename` + `output.chunkFilename` pattern below.
     *
     * The key names are used as the [name] token in `output.filename`.
     */
    entry: {
        'cart/build/index': path.resolve(__dirname, 'blocks/cart/src/index.tsx'),
        'cart-item/build/index': path.resolve(__dirname, 'blocks/cart-item/src/index.tsx'),
        'checkout/build/index': path.resolve(__dirname, 'blocks/checkout/src/index.tsx'),
        'account-tab-orders/build/index': path.resolve(__dirname, 'blocks/account-tab-orders/src/index.tsx'),
        'add-to-cart/build/index': path.resolve(__dirname, 'blocks/add-to-cart/src/index.tsx'),
        'currency-switcher/build/index': path.resolve(__dirname, 'blocks/currency-switcher/src/index.tsx'),
    },

    output: {
        ...defaultConfig.output,
        /**
         * Place each bundle directly into its block's build/ folder.
         * e.g. blocks/cart/build/index.js
         */
        path: path.resolve(__dirname, 'blocks'),
        filename: '[name].js',
    },
};
