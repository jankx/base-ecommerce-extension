<?php
namespace Jankx\Extensions\Ecommerce\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Blocks\CheckoutPaymentMethodsBlock;

class CheckoutPaymentMethodsBlockTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
        stub_wp_ecommerce_functions();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    protected function renderCreditCardPanel(): string
    {
        $block = new CheckoutPaymentMethodsBlock();

        $method = new \ReflectionMethod($block, 'renderCreditCardPanel');
        $method->setAccessible(true);

        return (string) $method->invoke($block);
    }

    public function test_panel_renders_every_card_field(): void
    {
        $html = $this->renderCreditCardPanel();

        $this->assertStringContainsString('id="jankx_card_number"', $html);
        $this->assertStringContainsString('id="jankx_card_expiry"', $html);
        $this->assertStringContainsString('id="jankx_card_cvv"', $html);
        $this->assertStringContainsString('id="jankx_card_holder"', $html);
    }

    public function test_panel_renders_card_brand_slot(): void
    {
        $html = $this->renderCreditCardPanel();

        $this->assertStringContainsString('class="jankx-card-brand"', $html);
        // The slot starts empty and hidden - frontend.js only fills it in once
        // the typed IIN matches a known scheme.
        $this->assertMatchesRegularExpression('/<span class="jankx-card-brand"[^>]*hidden>/', $html);
        $this->assertStringNotContainsString('data-card-brand=', $html);
    }

    public function test_brand_slot_wraps_the_card_number_input(): void
    {
        $html = $this->renderCreditCardPanel();

        $wrap = $this->extract('/<div class="jankx-card-number-wrap">.*?<\/div>/s', $html);

        $this->assertNotSame('', $wrap, 'Card number field must be wrapped for badge positioning');
        $this->assertStringContainsString('id="jankx_card_number"', $wrap);
        $this->assertStringContainsString('jankx-card-brand', $wrap);
    }

    public function test_card_number_input_is_numeric(): void
    {
        $html = $this->renderCreditCardPanel();

        $input = $this->extract('/<input[^>]*id="jankx_card_number"[^>]*>/', $html);

        $this->assertNotSame('', $input);
        $this->assertStringContainsString('autocomplete="cc-number"', $input);
        $this->assertStringContainsString('inputmode="numeric"', $input);
        $this->assertStringContainsString('maxlength="19"', $input);
    }

    protected function extract(string $pattern, string $subject): string
    {
        if (!preg_match($pattern, $subject, $matches)) {
            return '';
        }

        return $matches[0];
    }
}
