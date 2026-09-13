<?php
namespace Jankx\Extensions\Ecommerce\Order;

/**
 * Class OrderBuilder
 *
 * Implements the Builder Pattern to construct and persist Order entities cleanly.
 *
 * @package Jankx\Extensions\Ecommerce\Order
 */
class OrderBuilder
{
    protected int $customerId = 0;
    protected string $customerName = '';
    protected string $customerEmail = '';
    protected string $customerPhone = '';
    protected string $customerAddress = '';
    protected float $total = 0.0;
    protected string $currency = 'VND';
    protected string $paymentMethod = '';
    protected string $status = 'pending';
    protected string $trackingNumber = '';
    protected array $items = [];
    protected array $notes = [];
    protected array $history = [];
    protected array $linkedPosts = [];

    public static function create(): self
    {
        return new self();
    }

    public function setCustomer(
        string $name,
        string $email = '',
        string $phone = '',
        string $address = '',
        int $customerId = 0
    ): self {
        $this->customerName = trim($name);
        $this->customerEmail = trim($email);
        $this->customerPhone = trim($phone);
        $this->customerAddress = trim($address);
        $this->customerId = $customerId ?: (int) get_current_user_id();

        return $this;
    }

    public function setCustomerId(int $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function addItem(
        int $productId,
        string $name,
        string $productType = '',
        int $quantity = 1,
        float $unitPrice = 0.0,
        array $meta = []
    ): self {
        $this->items[] = [
            'product_id'   => $productId,
            'name'         => $name,
            'product_type' => $productType,
            'quantity'     => max(1, $quantity),
            'unit_price'   => $unitPrice,
            'meta'         => $meta,
        ];

        if ($productId > 0) {
            $this->linkedPosts[] = [
                'post_id'    => $productId,
                'post_type'  => $productType,
                'quantity'   => max(1, $quantity),
                'unit_price' => $unitPrice,
            ];
        }

        return $this;
    }

    public function setItems(array $items): self
    {
        $this->items = $items;
        $this->linkedPosts = [];
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $this->linkedPosts[] = [
                    'post_id'    => (int) $item['product_id'],
                    'post_type'  => (string) ($item['product_type'] ?? ''),
                    'quantity'   => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                ];
            }
        }
        return $this;
    }

    public function setTotal(float $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency ?: 'VND';
        return $this;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setTrackingNumber(string $trackingNumber): self
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function addNote(string $note, bool $isCustomer = false, int $userId = 0): self
    {
        if (trim($note) !== '') {
            $this->notes[] = [
                'note'       => trim($note),
                'customer'   => $isCustomer,
                'user_id'    => $userId ?: get_current_user_id(),
                'created_at' => current_time('mysql'),
            ];
        }
        return $this;
    }

    public function addHistory(string $action, string $from = '', string $to = '', string $note = '', int $userId = 0): self
    {
        $this->history[] = [
            'action'     => $action,
            'from'       => $from,
            'to'         => $to,
            'note'       => $note,
            'user_id'    => $userId ?: get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];
        return $this;
    }

    /**
     * Build and persist the Order to the database.
     */
    public function build(): ?Order
    {
        $orderData = [
            'status'           => $this->status,
            'customer_id'      => $this->customerId,
            'customer_name'    => $this->customerName,
            'customer_email'   => $this->customerEmail,
            'customer_phone'   => $this->customerPhone,
            'customer_address' => $this->customerAddress,
            'total'            => $this->total,
            'currency'         => $this->currency,
            'payment_method'   => $this->paymentMethod,
            'tracking_number'  => $this->trackingNumber,
            'items'            => $this->items,
            'notes'            => $this->notes,
            'history'          => $this->history,
        ];

        $orderId = OrderModel::create($orderData);
        if (!$orderId) {
            return null;
        }

        // Generate and assign order number
        $orderNumber = OrderModel::generateOrderNumber($orderId);
        OrderModel::update($orderId, ['order_number' => $orderNumber]);

        // Link posts to the order in jankx_order_posts
        foreach ($this->linkedPosts as $link) {
            OrderModel::createPostLink(
                $orderId,
                $link['post_id'],
                $link['post_type'],
                $link['quantity'],
                $link['unit_price']
            );
        }

        return new Order($orderId);
    }
}
