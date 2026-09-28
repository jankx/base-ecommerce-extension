<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;

class OrderProgressStepBlock extends Block
{
    protected $blockId = 'jankx/order-progress-step';

    private const STATES = [
        'todo'     => '',
        'active'   => 'jankx-od-step--active',
        'done'     => 'jankx-od-step--done',
        'terminal' => 'jankx-od-step--terminal jankx-od-step--done',
    ];

    private const ICONS = [
        'none'         => '',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'spinner'      => '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>',
        'truck'        => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'x-circle'     => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'rotate-ccw'   => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    ];

    public function render($attributes, $content = '', $block = null): string
    {
        $state = isset($attributes['state']) ? $attributes['state'] : 'todo';
        if (!isset(self::STATES[$state])) {
            $state = 'todo';
        }

        $icon = isset($attributes['icon']) && isset(self::ICONS[$attributes['icon']])
            ? $attributes['icon']
            : 'none';

        $classes = trim('jankx-od-step ' . self::STATES[$state]);
        $wrapper = get_block_wrapper_attributes(['class' => $classes]);

        return sprintf(
            '<div %s><span class="jankx-od-step-dot">%s</span><span class="jankx-od-step-label">%s</span></div>',
            $wrapper,
            $this->renderIcon(self::ICONS[$icon]),
            esc_html((string) ($attributes['label'] ?? ''))
        );
    }

    protected function renderIcon(string $iconPath): string
    {
        if ($iconPath === '') {
            return '';
        }

        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            . $iconPath
            . '</svg>';
    }
}