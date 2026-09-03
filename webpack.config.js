/**
 * webpack.config.js – base-ecommerce extension
 *
 * Extends the default @wordpress/scripts webpack config to build all
 * Gutenberg blocks inside this extension. Each block gets its own
 * output chunk so WordPress can `register_block_type()` them individually.
 *
 * Build:  npm run build:base-ecommerce
 *   (defined in the root theme's package.json)
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

// Base directory of this extension (the folder containing this config file).
const EXTENSION_DIR = __dirname;

// Filter out plugins that either target a different src root or cause
// double-nesting when the output path differs from the default.
const filteredPlugins = (defaultConfig.plugins || []).filter((plugin) => {
    const name = plugin.constructor?.name ?? '';
    // Remove CopyWebpackPlugin — block.json files are already in their
    // final location next to the compiled output; no copy needed.
    // Remove CleanWebpackPlugin — we manage the output path ourselves.
    return name !== 'CopyPlugin' && name !== 'CleanWebpackPlugin';
});

module.exports = {
    ...defaultConfig,
    context: EXTENSION_DIR,

    /**
     * One entry per block.
     */
    entry: {
        'blocks/cart/build/index': './blocks/cart/src/index.tsx',
        'blocks/cart-item/build/index': './blocks/cart-item/src/index.tsx',
        'blocks/checkout/build/index': './blocks/checkout/src/index.tsx',
        'blocks/account-tab-orders/build/index': './blocks/account-tab-orders/src/index.tsx',
        'blocks/add-to-cart/build/index': './blocks/add-to-cart/src/index.tsx',
        'blocks/currency-switcher/build/index': './blocks/currency-switcher/src/index.tsx',
    },

    output: {
        ...defaultConfig.output,
        path: EXTENSION_DIR,
        filename: '[name].js',
        clean: false,
    },

    plugins: filteredPlugins,
};
