<?php
namespace Jankx\Extensions\Ecommerce\Order\Strategies;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderBuilder;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

/**
 * Class ManualFormOrderStrategy
 *
 * Implements Strategy Pattern for creating orders submitted directly via product forms.
 * Orders created with this strategy have no initial price (total = 0.00, awaiting manual quote)
 * and are processed manually by staff.
 *
 * @package Jankx\Extensions\Ecommerce\Order\Strategies
 */
class ManualFormOrderStrategy extends AbstractOrderCreationStrategy
{
    const STRATEGY_NAME = 'manual_form';

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function validate(array $data): array
    {
        $errors = $this->validateCustomerData($data);

        $productId = (int) ($data['product_id'] ?? 0);
        if ($productId <= 0) {
            $errors[] = __('Sản phẩm không hợp lệ.', 'jankx');
        } else {
            $post = get_post($productId);
            if (!$post || $post->post_status !== 'publish') {
                $errors[] = __('Sản phẩm không tồn tại hoặc chưa được công khai.', 'jankx');
            }
        }

        return apply_filters('jankx/ecommerce/order/strategy/manual_form/validate', $errors, $data);
    }

    public function createOrder(array $data): ?Order
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return null;
        }

        $productId = (int) ($data['product_id'] ?? 0);
        $post = get_post($productId);
        $productTitle = $post ? get_the_title($post) : __('Sản phẩm không xác định', 'jankx');
        $postType = $post ? $post->post_type : 'product';

        $name = sanitize_text_field($data['customer_name'] ?? $data['name'] ?? '');
        $phone = sanitize_text_field($data['customer_phone'] ?? $data['phone'] ?? '');
        $email = sanitize_email($data['customer_email'] ?? $data['email'] ?? '');
        $address = sanitize_text_field($data['customer_address'] ?? $data['address'] ?? '');
        $customerNote = sanitize_textarea_field($data['note'] ?? $data['message'] ?? '');
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        $customerId = get_current_user_id();
        $currency = class_exists(CurrencyManager::class)
            ? CurrencyManager::getDefaultCurrency()
            : 'VND';

        $unitPrice = 0.00;
        $product = ProductRegistry::get_instance()->createProduct($productId);
        if ($product) {
            $unitPrice = (float) $product->getPrice();
        }

        $total = $unitPrice * $quantity;

        $builder = OrderBuilder::create()
            ->setCustomer($name, $email, $phone, $address, $customerId)
            ->addItem(
                $productId,
                $productTitle,
                $postType,
                $quantity,
                $unitPrice,
                [
                    'source'     => 'product_form',
                    'order_type' => $unitPrice > 0 ? 'manual_order' : 'manual_quote',
                ]
            )
            ->setTotal($unitPrice * $quantity)
            ->setCurrency($currency)
            ->setPaymentMethod('manual')
            ->setStatus(Order::STATUS_PENDING);

        if ($customerNote !== '') {
            $builder->addNote($customerNote, true);
        }

        if ($unitPrice > 0) {
            $builder->addNote(
                sprintf(
                    __('Đơn hàng được đặt qua form sản phẩm "%s" với đơn giá %s.', 'jankx'),
                    $productTitle,
                    CurrencyManager::formatPrice($unitPrice)
                ),
                false
            );
        } else {
            $builder->addNote(
                sprintf(
                    __('Đơn hàng được đặt qua form sản phẩm "%s". Sản phẩm chưa có giá, cần liên hệ và xử lý báo giá thủ công.', 'jankx'),
                    $productTitle
                ),
                false
            );
        }

        $order = $builder->build();

        if ($order) {
            do_action('jankx/ecommerce/order/created', $order, null);
            do_action('jankx/ecommerce/order/form_order_created', $order, $data);
        }

        return $order;
    }
}
