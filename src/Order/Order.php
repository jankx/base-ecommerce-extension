<?php
namespace Jankx\Extensions\Ecommerce\Order;

use Jankx\Extensions\Ecommerce\Abstracts\AbstractOrder;
use Jankx\Extensions\Ecommerce\Cart\Cart;

/**
 * Concrete order backed by the jankx_orders table.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class Order extends AbstractOrder
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPING = 'shipping';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Create an order from cart items + customer data.
     */
    public static function createFromCart(Cart $cart, array $customer, array $options = []): ?self
    {
        if ($cart->isEmpty()) {
            return null;
        }

        $items = [];
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $items[] = [
                'product_id'   => $cartItem->getProductId(),
                'name'         => $cartItem->getName(),
                'product_type' => $product ? $product->getProductType() : '',
                'quantity'     => $cartItem->getQuantity(),
                'unit_price'   => $cartItem->getUnitPrice(),
                'meta'         => $cartItem->getArgs(),
            ];
        }

        $total = $cart->getTotal();
        $customerId = (int) ($customer['id'] ?? get_current_user_id());

        $orderId = OrderModel::create([
            'items'       => $items,
            'total'       => $total,
            'currency'    => $options['currency'] ?? 'VND',
            'customer_id' => $customerId,
            'customer_name'   => $customer['name'] ?? '',
            'customer_email'  => $customer['email'] ?? '',
            'customer_phone'  => $customer['phone'] ?? '',
            'customer_address' => $customer['address'] ?? '',
            'payment_method'  => $options['gateway'] ?? '',
        ]);

        if (!$orderId) {
            return null;
        }

        // Generate and save order number
        $orderNumber = OrderModel::generateOrderNumber($orderId);
        OrderModel::update($orderId, ['order_number' => $orderNumber]);

        $order = new self($orderId);

        do_action('jankx/ecommerce/order/created', $order, $cart);

        return $order;
    }

    /**
     * Find an order by its order number.
     */
    public static function findByOrderNumber(string $orderNumber): ?self
    {
        $row = OrderModel::findByOrderNumber($orderNumber);
        if (!$row) {
            return null;
        }
        return new self($row['id']);
    }

    /**
     * Query orders with filters.
     */
    public static function query(array $args = []): array
    {
        $rows = OrderModel::query($args);
        return array_map(function ($row) {
            return new self($row['id']);
        }, $rows);
    }

    /**
     * Count orders with filters.
     */
    public static function countOrders(array $args = []): int
    {
        return OrderModel::count($args);
    }

    public function getCustomerId(): int
    {
        return (int) ($this->data['customer_id'] ?? 0);
    }

    public function getCustomerName(): string
    {
        return (string) ($this->data['customer_name'] ?? '');
    }

    public function getCustomerEmail(): string
    {
        return (string) ($this->data['customer_email'] ?? '');
    }

    public function getCustomerPhone(): string
    {
        return (string) ($this->data['customer_phone'] ?? '');
    }

    public function getCustomerAddress(): string
    {
        return (string) ($this->data['customer_address'] ?? '');
    }

    public function getCurrency(): string
    {
        return (string) ($this->data['currency'] ?? 'VND');
    }

    public function getPaymentMethod(): string
    {
        return (string) ($this->data['payment_method'] ?? '');
    }

    public function getTotal(): float
    {
        return (float) ($this->data['total'] ?? 0);
    }

    public function getStatus(): string
    {
        $status = $this->data['status'] ?? self::STATUS_PENDING;
        return $status ?: self::STATUS_PENDING;
    }

    /**
     * Get the list of statuses this order can transition to next.
     */
    public function getAllowedStatusTransitions(): array
    {
        return self::getAllowedTransitions()[$this->getStatus()] ?? [];
    }

    /**
     * Static helper: get allowed transitions for a given status string.
     */
    public static function getAllowedStatusTransitionsFor(string $status): array
    {
        return self::getAllowedTransitions()[$status] ?? [];
    }

    /**
     * Update the order status and record a history entry with the handler.
     */
    public function updateStatus(string $newStatus, string $note = '', int $handlerId = 0): bool
    {
        $newStatus = sanitize_key($newStatus);
        $oldStatus = $this->getStatus();
        if ($oldStatus === $newStatus) {
            return true;
        }

        $result = OrderModel::updateStatus($this->getId(), $newStatus, $note, $handlerId);
        if ($result) {
            // Reload data
            $this->data = OrderModel::findById($this->getId());
            do_action('jankx/ecommerce/order/status_changed', $this, $newStatus, $oldStatus, $handlerId);
        }

        return $result;
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array
    {
        $raw = $this->data['items'] ?? [];

        return array_map(function (array $data) {
            return new OrderItem($data);
        }, $raw);
    }

    public function addNote(string $note, bool $isCustomerNote = false, int $userId = 0): void
    {
        if (!$note) {
            return;
        }

        OrderModel::appendNote($this->getId(), $note, $isCustomerNote);

        // Reload data
        $this->data = OrderModel::findById($this->getId());

        do_action('jankx/ecommerce/order/note_added', $this, $note, $isCustomerNote);
    }

    public function getNotes(bool $customerOnly = false): array
    {
        $notes = $this->data['notes'] ?? [];

        return $customerOnly
            ? array_values(array_filter($notes, function ($n) {
                return !empty($n['customer']);
            }))
            : $notes;
    }

    /**
     * The user ID who last handled (changed the status of) this order.
     */
    public function getHandlerId(): int
    {
        return (int) ($this->data['handler_id'] ?? 0);
    }

    /**
     * Display name of the last handler (empty when unknown).
     */
    public function getHandler(): string
    {
        $handlerId = $this->getHandlerId();
        if (!$handlerId) {
            return '';
        }

        $user = get_userdata($handlerId);
        return $user ? $user->display_name : '';
    }

    /**
     * Full order history: status transitions + notes with handlers.
     */
    public function getHistory(): array
    {
        return $this->data['history'] ?? [];
    }

    /**
     * Default status transition map.
     */
    public static function getAllowedTransitions(): array
    {
        $transitions = [
            self::STATUS_PENDING    => [self::STATUS_PROCESSING, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SHIPPING, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPING   => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED  => [self::STATUS_REFUNDED],
            self::STATUS_FAILED     => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED  => [self::STATUS_PENDING],
            self::STATUS_REFUNDED   => [],
        ];

        return apply_filters('jankx/ecommerce/order/status_transitions', $transitions);
    }

    /**
     * All order statuses with their labels.
     */
    public static function getStatusLabels(): array
    {
        $labels = [
            self::STATUS_PENDING    => __('Pending', 'jankx'),
            self::STATUS_PROCESSING => __('Processing', 'jankx'),
            self::STATUS_SHIPPING   => __('Đang vận chuyển', 'jankx'),
            self::STATUS_COMPLETED  => __('Completed', 'jankx'),
            self::STATUS_FAILED     => __('Failed', 'jankx'),
            self::STATUS_CANCELLED  => __('Cancelled', 'jankx'),
            self::STATUS_REFUNDED   => __('Refunded', 'jankx'),
        ];

        return apply_filters('jankx/ecommerce/order/status_labels', $labels);
    }

    public static function getStatusLabel(string $status): string
    {
        $labels = self::getStatusLabels();
        return $labels[$status] ?? ucfirst($status);
    }

    public function getPaymentTransactionId(): int
    {
        return (int) ($this->data['payment_transaction_id'] ?? 0);
    }

    public function setPaymentTransactionId(int $transactionId): void
    {
        OrderModel::update($this->getId(), ['payment_transaction_id' => $transactionId]);
        $this->data['payment_transaction_id'] = $transactionId;
    }

    public function getTrackingNumber(): string
    {
        return (string) ($this->data['tracking_number'] ?? '');
    }

    public function setTrackingNumber(string $trackingNumber): void
    {
        OrderModel::update($this->getId(), ['tracking_number' => $trackingNumber]);
        $this->data['tracking_number'] = $trackingNumber;
    }

    public function getDateCreated(): string
    {
        return $this->data['created_at'] ?? '';
    }

    public function getLinkedPosts(): array
    {
        return OrderModel::getOrderPosts($this->getId());
    }

    public function toArray(): array
    {
        if (!$this->data) {
            return [];
        }

        return [
            'id'              => $this->getId(),
            'order_number'    => $this->getOrderNumber(),
            'status'          => $this->getStatus(),
            'status_label'    => self::getStatusLabel($this->getStatus()),
            'handler_id'      => $this->getHandlerId(),
            'handler'         => $this->getHandler(),
            'customer_id'     => $this->getCustomerId(),
            'customer_name'   => $this->getCustomerName(),
            'customer_email'  => $this->getCustomerEmail(),
            'customer_phone'  => $this->getCustomerPhone(),
            'customer_address' => $this->getCustomerAddress(),
            'total'           => $this->getTotal(),
            'currency'        => $this->getCurrency(),
            'payment_method'  => $this->getPaymentMethod(),
            'tracking_number' => $this->getTrackingNumber(),
            'created_at'      => $this->getDateCreated(),
            'history'         => $this->getHistory(),
            'items'           => array_map(function (OrderItem $item) {
                return $item->toArray();
            }, $this->getItems()),
        ];
    }
}
