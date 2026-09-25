<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

/**
 * Post Price block.
 *
 * Displays the price of the current product (tour, experience, product, ...)
 * through the shared ProductRegistry API. This is the single source of truth
 * for the displayed price, so it always matches what "Add to Cart" charges.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class PostPriceBlock extends Block
{
    const BLOCK_ID = 'jankx/post-price';

    protected $blockId = self::BLOCK_ID;

    public function render($attributes, $content = '', $block = null)
    {
        $isTemplateEditor = $this->isTemplateEditor();

        $product = null;
        $postId = 0;

        if ($isTemplateEditor) {
            $price = (float) $this->getMockPrice();
        } else {
            $postId = $this->resolvePostId($block);
            if (!$postId) {
                return '';
            }

            $product = ProductRegistry::get_instance()->createProduct($postId);
            $price = $product ? (float) $product->getPrice() : 0.0;
        }

        // Allow business extensions to override the displayed price with
        // their own logic (e.g. date-based tour pricing).
        $price = (float) apply_filters('jankx/ecommerce/product/price', $price, $postId, $product);

        $defaultCurrency = CurrencyManager::getDefaultCurrency();
        $sourceCurrency = $defaultCurrency;
        $targetCurrency = CurrencyManager::getCurrentCurrency();

        $prefix = $attributes['prefix'] ?? 'Từ ';
        $suffix = $attributes['suffix'] ?? '/ người';
        $showWhenEmpty = $attributes['showWhenEmpty'] ?? true;
        $emptyText = $attributes['emptyText'] ?? 'Liên hệ';
        $tagName = $attributes['tagName'] ?? 'span';

        $allowedTags = ['span', 'div', 'p', 'strong'];
        if (!in_array($tagName, $allowedTags, true)) {
            $tagName = 'span';
        }

        if ($price <= 0) {
            if (!$showWhenEmpty) {
                return '';
            }
            $formattedPrice = esc_html($emptyText);
        } else {
            $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
            $formattedPrice = $converterManager->formatPriceWithConversion(
                $price,
                $sourceCurrency,
                $targetCurrency
            );
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'wp-block-jankx-post-price',
            'style' => $this->buildInlineStyle($attributes),
        ]);

        ob_start();
        ?>
        <<?php echo esc_attr($tagName); ?>         <?php echo $wrapperAttrs; ?>>
            <?php
            if (!empty($prefix)) {
                echo '<span class="post-price__prefix">' . esc_html($prefix) . '</span>';
            }
            ?><span
                class="post-price__price"><?php echo $formattedPrice; ?></span>
            <?php
            if (!empty($suffix)) {
                echo '<span class="post-price__suffix">' . esc_html($suffix) . '</span>';
            }
            ?>
        </<?php echo esc_attr($tagName); ?>>
        <?php
        return ob_get_clean();
    }

    /**
     * Build inline style string from block attributes (typography, color, border).
     */
    protected function buildInlineStyle(array $attributes): string
    {
        $parts = [];
        $style = $attributes['style'] ?? [];

        $typo = $style['typography'] ?? [];
        if (!empty($typo['fontSize']))       $parts[] = 'font-size: ' . esc_attr($typo['fontSize']);
        if (!empty($typo['lineHeight']))     $parts[] = 'line-height: ' . esc_attr($typo['lineHeight']);
        if (!empty($typo['fontFamily']))     $parts[] = 'font-family: ' . esc_attr($typo['fontFamily']);
        if (!empty($typo['fontWeight']))     $parts[] = 'font-weight: ' . esc_attr($typo['fontWeight']);
        if (!empty($typo['fontStyle']))      $parts[] = 'font-style: ' . esc_attr($typo['fontStyle']);
        if (!empty($typo['textTransform']))  $parts[] = 'text-transform: ' . esc_attr($typo['textTransform']);
        if (!empty($typo['textDecoration'])) $parts[] = 'text-decoration: ' . esc_attr($typo['textDecoration']);
        if (!empty($typo['letterSpacing']))  $parts[] = 'letter-spacing: ' . esc_attr($typo['letterSpacing']);

        $color = $style['color'] ?? [];
        if (!empty($color['text']))        $parts[] = 'color: ' . esc_attr($color['text']);
        if (!empty($color['background']))  $parts[] = 'background-color: ' . esc_attr($color['background']);

        $border = $style['border'] ?? [];
        if (!empty($border['color']))   $parts[] = 'border-color: ' . esc_attr($border['color']);
        if (!empty($border['radius']))  $parts[] = 'border-radius: ' . esc_attr($border['radius']);
        if (!empty($border['style']))   $parts[] = 'border-style: ' . esc_attr($border['style']);
        if (!empty($border['width']))   $parts[] = 'border-width: ' . esc_attr($border['width']);

        return implode('; ', $parts);
    }

    protected function resolvePostId($block): int
    {
        if ($block instanceof \WP_Block && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        $postId = get_the_ID();
        if ($postId) {
            return (int) $postId;
        }

        global $post;
        if ($post && isset($post->ID)) {
            return (int) $post->ID;
        }

        return 0;
    }

    /**
     * Check whether the block is rendered inside the template editor.
     */
    protected function isTemplateEditor(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (
                strpos($request_uri, '/wp-json/wp/v2/template') !== false ||
                strpos($request_uri, '/wp-json/wp/v2/template-part') !== false
            ) {
                return true;
            }
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && ($screen->id === 'site-editor' || $screen->id === 'appearance_page_gutenberg-edit-site')) {
                return true;
            }
        }

        global $post;
        if (
            (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) &&
            (empty($post) || empty($post->post_content))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get mock price for template editor preview.
     */
    protected function getMockPrice(): string
    {
        return '4500000';
    }
}