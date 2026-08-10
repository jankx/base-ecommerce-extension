<?php
namespace Jankx\Extensions\Ecommerce\Order;

use Jankx\Extensions\Ecommerce\Abstracts\AbstractOrder;
use Jankx\Extensions\Ecommerce\Cart\Cart;

/**
 * Concrete order backed by the jankx_order post type.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class Order extends AbstractOrder
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
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
            $items[] = [
                'product_id'   => $cartItem->getProductId(),
                'name'         => $cartItem->getName(),
                'product_type' => $cartItem->getProduct() ? $cartItem->getProduct()->getProductType() : '',
                'quantity'     => $cartItem->getQuantity(),
                'unit_price'   => $cartItem->getUnitPrice(),
                'meta'         => $cartItem->getArgs(),
            ];
        }

        $total = $cart->getTotal();
        $customerId = (int) ($customer['id'] ?? get_current_user_id());

        $postId = wp_insert_post([
            'post_type'   => OrderPostType::POST_TYPE,
            'post_title'  => sprintf(
                __('Order %s', 'jankx'),
                current_time('YmdHis')
            ),
            'post_status' => 'publish',
            'meta_input'  => [
                '_order_status'        => self::STATUS_PENDING,
                '_customer_id'         => $customerId,
                '_customer_name'       => $customer['name'] ?? '',
                '_customer_email'      => $customer['email'] ?? '',
                '_customer_phone'      => $customer['phone'] ?? '',
                '_customer_address'    => $customer['address'] ?? '',
                '_order_items'         => $items,
                '_order_total'         => $total,
                '_order_currency'      => $options['currency'] ?? 'VND',
                '_payment_method'      => $options['gateway'] ?? '',
                '_order_notes'         => [],
            ],
        ], true);

        if (is_wp_error($postId)) {
            return null;
        }

        $order = new self($postId);
        update_post_meta($postId, '_order_number', $order->generateOrderNumber($postId));

        do_action('jankx/ecommerce/order/created', $order, $cart);

        return $order;
    }

    protected function generateOrderNumber(int $postId): string
    {
        return apply_filters('jankx/ecommerce/order/number', sprintf('OD-%06d', $postId), $postId);
    }

    public function getCustomerId(): int
    {
        return (int) get_post_meta($this->getId(), '_customer_id', true);
    }

    public function getCustomerName(): string
    {
        return (string) get_post_meta($this->getId(), '_customer_name', true);
    }

    public function getCustomerEmail(): string
    {
        return (string) get_post_meta($this->getId(), '_customer_email', true);
    }

    public function getCustomerPhone(): string
    {
        return (string) get_post_meta($this->getId(), '_customer_phone', true);
    }

    public function getCustomerAddress(): string
    {
        return (string) get_post_meta($this->getId(), '_customer_address', true);
    }

    public function getCurrency(): string
    {
        return (string) get_post_meta($this->getId(), '_order_currency', true);
    }

    public function getPaymentMethod(): string
    {
        return (string) get_post_meta($this->getId(), '_payment_method', true);
    }

    public function getTotal(): float
    {
        return (float) get_post_meta($this->getId(), '_order_total', true);
    }

    public function getStatus(): string
    {
        $status = get_post_meta($this->getId(), '_order_status', true);

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
     * Update the order status and record a history entry with the handler.
     *
     * @param string $newStatus New order status.
     * @param string $note      Optional note attached to the transition.
     * @param int    $handlerId User ID who performed the change (0 = current user).
     */
    public function updateStatus(string $newStatus, string $note = '', int $handlerId = 0): bool
    {
        $newStatus = sanitize_key($newStatus);
        $oldStatus = $this->getStatus();
        if ($oldStatus === $newStatus) {
            return true;
        }

        $handlerId = $handlerId ?: get_current_user_id();

        update_post_meta($this->getId(), '_order_status', $newStatus);
        update_post_meta($this->getId(), '_order_handler_id', $handlerId);

        $this->recordHistory([
            'action'     => 'status',
            'from'       => $oldStatus,
            'to'         => $newStatus,
            'note'       => $note,
            'user_id'    => $handlerId,
            'created_at' => current_time('mysql'),
        ]);

        if ($note) {
            $this->appendNote($note, false, $handlerId);
        }

        do_action('jankx/ecommerce/order/status_changed', $this, $newStatus, $oldStatus, $handlerId);

        return true;
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array
    {
        $raw = get_post_meta($this->getId(), '_order_items', true);
        $raw = is_array($raw) ? $raw : [];

        return array_map(function (array $data) {
            return new OrderItem($data);
        }, $raw);
    }

    public function addNote(string $note, bool $isCustomerNote = false, int $userId = 0): void
    {
        if (!$note) {
            return;
        }

        $this->appendNote($note, $isCustomerNote, $userId);

        $this->recordHistory([
            'action'     => 'note',
            'note'       => sanitize_textarea_field($note),
            'customer'   => (bool) $isCustomerNote,
            'user_id'    => $userId ?: get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        do_action('jankx/ecommerce/order/note_added', $this, $note, $isCustomerNote);
    }

    /**
     * Append a note to _order_notes without recording a history entry.
     */
    protected function appendNote(string $note, bool $isCustomerNote = false, int $userId = 0): void
    {
        if (!$note) {
            return;
        }

        $notes = get_post_meta($this->getId(), '_order_notes', true);
        $notes = is_array($notes) ? $notes : [];

        $notes[] = [
            'note'       => sanitize_textarea_field($note),
            'customer'   => (bool) $isCustomerNote,
            'user_id'    => $userId ?: get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];

        update_post_meta($this->getId(), '_order_notes', $notes);
    }

    public function getNotes(bool $customerOnly = false): array
    {
        $notes = get_post_meta($this->getId(), '_order_notes', true);
        $notes = is_array($notes) ? $notes : [];

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
        return (int) get_post_meta($this->getId(), '_order_handler_id', true);
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
     *
     * @return array[]
     */
    public function getHistory(): array
    {
        $history = get_post_meta($this->getId(), '_order_history', true);

        return is_array($history) ? $history : [];
    }

    protected function recordHistory(array $entry): void
    {
        $history = $this->getHistory();
        $history[] = $entry;

        update_post_meta($this->getId(), '_order_history', $history);
    }

    /**
     * Default status transition map. Extendable via the
     * `jankx/ecommerce/order/status_transitions` filter.
     */
    public static function getAllowedTransitions(): array
    {
        $transitions = [
            self::STATUS_PENDING    => [self::STATUS_PROCESSING, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED  => [self::STATUS_REFUNDED],
            self::STATUS_FAILED     => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED  => [self::STATUS_PENDING],
            self::STATUS_REFUNDED   => [],
        ];

        return apply_filters('jankx/ecommerce/order/status_transitions', $transitions);
    }

    /**
     * All order statuses with their labels.
     *
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        $labels = [
            self::STATUS_PENDING    => __('Pending', 'jankx'),
            self::STATUS_PROCESSING => __('Processing', 'jankx'),
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
        return (int) get_post_meta($this->getId(), '_payment_transaction_id', true);
    }

    public function setPaymentTransactionId(int $transactionId): void
    {
        update_post_meta($this->getId(), '_payment_transaction_id', $transactionId);
    }

    public function getDateCreated(): string
    {
        return $this->post ? $this->post->post_date : '';
    }

    public function toArray(): array
    {
        if (!$this->post) {
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
            'created_at'      => $this->getDateCreated(),
            'history'         => $this->getHistory(),
            'items'           => array_map(function (OrderItem $item) {
                return $item->toArray();
            }, $this->getItems()),
        ];
    }
}
