<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class CurrencySwitcherBlock extends Block
{
    const BLOCK_ID = 'jankx/currency-switcher';

    protected function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function enqueueScripts(): void
    {
        if (!is_singular() && !is_page()) {
            return;
        }

        global $post;
        if (!$post || !has_block(self::BLOCK_ID, $post)) {
            return;
        }

        wp_enqueue_script(
            'jankx-currency-switcher',
            $this->getAssetsUrl('currency-switcher.js'),
            [],
            filemtime($this->getAssetsPath('currency-switcher.js')),
            true
        );

        wp_localize_script('jankx-currency-switcher', 'jankxCurrencySwitcher', [
            'restUrl' => esc_url_raw(rest_url('jankx/ecommerce/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentCurrency' => CurrencyManager::getCurrentCurrency(),
        ]);
    }

    public function render(array $attributes): string
    {
        // block.json uses 'displayMode'; fall back to legacy 'display' key just in case
        $display = $attributes['displayMode'] ?? $attributes['display'] ?? 'dropdown';
        $showFlag = $attributes['showFlag'] ?? true;
        $showCode = $attributes['showCode'] ?? true;
        $showSymbol = $attributes['showSymbol'] ?? false;
        $showName = $attributes['showName'] ?? false;

        $enabled = CurrencyManager::getEnabledCurrenciesList();
        $current = CurrencyManager::getCurrentCurrency();

        if (empty($enabled)) {
            return '';
        }

        // Determine the display mode first so the wrapper class is consistent,
        // then render the inner content. get_block_wrapper_attributes() is called
        // exactly once here (mirroring LanguageSwitcherBlock) so border/color/spacing
        // support styles from block.json are applied to a single consistent wrapper.
        $mode = 'dropdown';
        if (count($enabled) === 1) {
            $mode = 'single';
            $inner = $this->renderSingle(reset($enabled), $showFlag, $showCode, $showSymbol, $showName);
        } elseif ($display === 'buttons') {
            $mode = 'buttons';
            $inner = $this->renderButtons($enabled, $current, $showFlag, $showCode, $showSymbol, $showName);
        } elseif ($display === 'inline' || $display === 'list') {
            // 'inline' is the block.json value; 'list' is the legacy alias
            $mode = 'list';
            $inner = $this->renderList($enabled, $current, $showFlag, $showCode, $showSymbol, $showName);
        } else {
            $inner = $this->renderDropdown($enabled, $current, $showFlag, $showCode, $showSymbol, $showName);
        }

        return sprintf(
            '<div %s>%s</div>',
            get_block_wrapper_attributes([
                'class' => 'jcs-switcher jcs--' . $mode,
                'style' => $this->resolveWrapperStyle($attributes),
            ]),
            $inner
        );
    }

    /**
     * Resolve the inline style for the block wrapper.
     *
     * Returns the user-set background from the block supports when available,
     * otherwise falls back to a default white background so the switcher has a
     * consistent appearance on the frontend even for legacy blocks.
     *
     * @param array $attributes Block attributes.
     * @return string Inline CSS style string.
     */
    protected function resolveWrapperStyle(array $attributes): string
    {
        return '';
    }

    protected function renderSingle(array $currency, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $parts = [];
        if ($showFlag) {
            $parts[] = '<span class="jcs-flag">' . esc_html($currency['flag']) . '</span>';
        }
        if ($showCode) {
            $parts[] = '<span class="jcs-code">' . esc_html($currency['code']) . '</span>';
        }
        if ($showSymbol) {
            $parts[] = '<span class="jcs-symbol">' . esc_html($currency['symbol']) . '</span>';
        }
        if ($showName) {
            $parts[] = '<span class="jcs-name">' . esc_html($currency['name']) . '</span>';
        }

        return implode(' ', $parts);
    }

    protected function renderDropdown(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $currentCurrency = $currencies[$current] ?? reset($currencies);
        if (!is_array($currentCurrency)) {
            $currentCurrency = [];
        }

        // Mirrors LanguageSwitcherBlock dropdown structure (button + absolute menu)
        // so the two switcher blocks share the same frontend pattern.
        $html = '<div class="jcs-dropdown-wrapper">';
        $html .= '<button class="jcs-dropdown" type="button" aria-haspopup="true" aria-expanded="false">';
        $html .= $this->buildLabelHtml($currentCurrency, $showFlag, $showCode, $showSymbol, $showName);
        $html .= '<span class="jcs-arrow">▼</span>';
        $html .= '</button>';

        $html .= '<ul class="jcs-dropdown-menu">';
        foreach ($currencies as $code => $currency) {
            $isCurrent = $code === $current;
            $itemClasses = ['jcs-dropdown-item'];
            if ($isCurrent) {
                $itemClasses[] = 'current-currency';
            }

            $html .= '<li class="' . esc_attr(implode(' ', $itemClasses)) . '">';
            $html .= '<a href="#" class="jcs-dropdown-link" data-jcs-action="switch" data-jcs-currency="' . esc_attr($code) . '">';
            $html .= $this->buildLabelHtml($currency, $showFlag, $showCode, $showSymbol, $showName);
            $html .= '</a></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    protected function renderButtons(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $buttons = [];
        foreach ($currencies as $code => $currency) {
            $active = $code === $current ? ' jcs--active' : '';
            $url = esc_url(add_query_arg('currency', $code));
            // Đổi button sang thẻ link style button cho chuẩn SSR
            $buttons[] = '<a href="' . $url . '" class="jcs-btn' . $active . '" style="text-decoration:none;">'
                . $this->buildLabelHtml($currency, $showFlag, $showCode, $showSymbol, $showName)
                . '</a>';
        }

        return implode(' ', $buttons);
    }

    protected function renderList(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $html = '<ul class="jcs-list">';

        foreach ($currencies as $code => $currency) {
            $active = $code === $current ? ' jcs--active' : '';
            $url = esc_url(add_query_arg('currency', $code));

            $html .= '<li class="jcs-list-item' . $active . '">';
            $html .= '<a href="' . $url . '">';
            $html .= $this->buildLabelHtml($currency, $showFlag, $showCode, $showSymbol, $showName);
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function buildLabel(array $currency, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $parts = [];
        if ($showFlag) {
            $parts[] = $currency['flag'];
        }
        if ($showCode) {
            $parts[] = $currency['code'];
        }
        if ($showSymbol) {
            $parts[] = $currency['symbol'];
        }
        if ($showName) {
            $parts[] = $currency['name'];
        }
        return implode(' ', $parts) ?: $currency['code'];
    }

    protected function buildLabelHtml(array $currency, bool $showFlag, bool $showCode, bool $showSymbol, bool $showName = false): string
    {
        $html = '';
        if ($showFlag) {
            $html .= '<span class="jcs-flag">' . esc_html($currency['flag']) . '</span> ';
        }
        if ($showCode) {
            $html .= '<span class="jcs-code">' . esc_html($currency['code']) . '</span>';
        }
        if ($showSymbol) {
            $html .= ' <span class="jcs-symbol">(' . esc_html($currency['symbol']) . ')</span>';
        }
        if ($showName) {
            $html .= ' <span class="jcs-name">' . esc_html($currency['name']) . '</span>';
        }
        return $html ?: esc_html($currency['code']);
    }

    protected function getAssetsUrl(string $file): string
    {
        return dirname($this->blockPath ? dirname($this->blockPath) : dirname(__DIR__)) . '/assets/' . $file;
    }

    protected function getAssetsPath(string $file): string
    {
        return dirname($this->blockPath ? dirname($this->blockPath) : dirname(__DIR__)) . '/assets/' . $file;
    }
}
