<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class CurrencySwitcherBlock extends Block
{
    const BLOCK_ID = 'jankx/currency-switcher';

    protected function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
    }

    public function enqueueFrontendAssets(): void
    {
        if (!is_singular() && !is_page()) {
            return;
        }

        global $post;
        if (!$post || !has_block(self::BLOCK_ID, $post)) {
            return;
        }

        wp_enqueue_style(
            'jankx-currency-switcher',
            $this->getAssetsUrl('currency-switcher.css'),
            [],
            filemtime($this->getAssetsPath('currency-switcher.css'))
        );

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
        $display = $attributes['display'] ?? 'dropdown';
        $showFlag = $attributes['showFlag'] ?? true;
        $showCode = $attributes['showCode'] ?? true;
        $showSymbol = $attributes['showSymbol'] ?? false;

        $enabled = CurrencyManager::getEnabledCurrenciesList();
        $current = CurrencyManager::getCurrentCurrency();

        if (empty($enabled)) {
            return '';
        }

        if (count($enabled) === 1) {
            $currency = reset($enabled);
            return $this->renderSingle($currency, $showFlag, $showCode, $showSymbol);
        }

        if ($display === 'buttons') {
            return $this->renderButtons($enabled, $current, $showFlag, $showCode, $showSymbol);
        }

        if ($display === 'list') {
            return $this->renderList($enabled, $current, $showFlag, $showCode, $showSymbol);
        }

        return $this->renderDropdown($enabled, $current, $showFlag, $showCode, $showSymbol);
    }

    protected function renderSingle(array $currency, bool $showFlag, bool $showCode, bool $showSymbol): string
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

        return '<div class="jcs-switcher jcs--single">' . implode(' ', $parts) . '</div>';
    }

    protected function renderDropdown(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol): string
    {
        $html = '<div class="jcs-switcher jcs--dropdown">';
        $html .= '<select class="jcs-select" data-jcs-action="switch">';

        foreach ($currencies as $code => $currency) {
            $selected = $code === $current ? ' selected' : '';
            $label = $this->buildLabel($currency, $showFlag, $showCode, $showSymbol);
            $html .= '<option value="' . esc_attr($code) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }

    protected function renderButtons(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol): string
    {
        $html = '<div class="jcs-switcher jcs--buttons">';

        foreach ($currencies as $code => $currency) {
            $active = $code === $current ? ' jcs--active' : '';
            $html .= '<button type="button" class="jcs-btn' . $active . '" data-jcs-action="switch" data-jcs-currency="' . esc_attr($code) . '">';
            $html .= $this->buildLabelHtml($currency, $showFlag, $showCode, $showSymbol);
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    protected function renderList(array $currencies, string $current, bool $showFlag, bool $showCode, bool $showSymbol): string
    {
        $html = '<ul class="jcs-switcher jcs--list">';

        foreach ($currencies as $code => $currency) {
            $active = $code === $current ? ' jcs--active' : '';
            $html .= '<li class="jcs-list-item' . $active . '">';
            $html .= '<a href="#" data-jcs-action="switch" data-jcs-currency="' . esc_attr($code) . '">';
            $html .= $this->buildLabelHtml($currency, $showFlag, $showCode, $showSymbol);
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function buildLabel(array $currency, bool $showFlag, bool $showCode, bool $showSymbol): string
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
        return implode(' ', $parts) ?: $currency['code'];
    }

    protected function buildLabelHtml(array $currency, bool $showFlag, bool $showCode, bool $showSymbol): string
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
