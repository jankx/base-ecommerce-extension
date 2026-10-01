<?php
namespace Jankx\Extensions\Ecommerce\Order;

/**
 * Luồng trạng thái đơn hàng (order flow) kiểu Kanban/Trello.
 *
 * Flow gồm các NHÓM trạng thái (cột) và các LIÊN KẾT chuyển đổi giữa các nhóm.
 * Một trạng thái thuộc đúng một nhóm; chỉ được chuyển sang trạng thái ở nhóm
 * đích nếu tồn tại liên kết nhóm nguồn → nhóm đích.
 *
 * - Chưa cấu hình (option trống) → mặc định 7 nhóm khớp bảng transition cũ.
 * - Trạng thái chưa phân nhóm → fallback theo bảng transition legacy.
 */
class OrderFlow
{
    const OPTION = 'jankx_order_flow';

    public static function defaultFlow(): array
    {
        return [
            'groups' => [
                ['id' => 'pending', 'name' => 'Chờ thanh toán', 'color' => '#f59e0b', 'statuses' => ['pending']],
                ['id' => 'processing', 'name' => 'Đang xử lý', 'color' => '#2563eb', 'statuses' => ['processing']],
                ['id' => 'shipping', 'name' => 'Đang vận chuyển', 'color' => '#7c3aed', 'statuses' => ['shipping']],
                ['id' => 'completed', 'name' => 'Đã hoàn thành', 'color' => '#16a34a', 'statuses' => ['completed']],
                ['id' => 'failed', 'name' => 'Thanh toán thất bại', 'color' => '#dc2626', 'statuses' => ['failed']],
                ['id' => 'cancelled', 'name' => 'Đã hủy', 'color' => '#64748b', 'statuses' => ['cancelled']],
                ['id' => 'refunded', 'name' => 'Hoàn tiền', 'color' => '#d97706', 'statuses' => ['refunded']],
            ],
            'links' => [
                ['from' => 'pending', 'to' => 'processing'],
                ['from' => 'pending', 'to' => 'failed'],
                ['from' => 'pending', 'to' => 'cancelled'],
                ['from' => 'processing', 'to' => 'shipping'],
                ['from' => 'processing', 'to' => 'failed'],
                ['from' => 'processing', 'to' => 'cancelled'],
                ['from' => 'shipping', 'to' => 'completed'],
                ['from' => 'shipping', 'to' => 'failed'],
                ['from' => 'shipping', 'to' => 'cancelled'],
                ['from' => 'completed', 'to' => 'refunded'],
                ['from' => 'failed', 'to' => 'processing'],
                ['from' => 'failed', 'to' => 'cancelled'],
                ['from' => 'cancelled', 'to' => 'pending'],
            ],
        ];
    }

    /**
     * Flow hiện tại (option đã lưu) hoặc flow mặc định.
     */
    public static function getFlow(): array
    {
        $saved = get_option(self::OPTION, null);
        if (!is_array($saved) || empty($saved['groups'])) {
            $flow = self::defaultFlow();
        } else {
            $flow = self::sanitize($saved);
        }

        return apply_filters('jankx/ecommerce/order_flow', $flow);
    }

    /**
     * Validate + làm sạch payload từ client rồi lưu vào option.
     */
    public static function saveFlow(array $flow): array
    {
        $clean = self::sanitize($flow);
        if (empty($clean['groups'])) {
            $clean = self::defaultFlow();
        }
        update_option(self::OPTION, $clean, false);

        return $clean;
    }

    public static function sanitize(array $flow): array
    {
        $known = array_keys(Order::getStatusLabels());
        $groups = [];
        $groupIds = [];
        $assigned = [];

        foreach ((array) ($flow['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            $id = sanitize_key($group['id'] ?? '');
            if ($id === '' || isset($groupIds[$id])) {
                continue;
            }
            $statuses = [];
            foreach ((array) ($group['statuses'] ?? []) as $status) {
                $status = sanitize_key($status);
                if (in_array($status, $known, true) && !isset($assigned[$status])) {
                    $statuses[] = $status;
                    $assigned[$status] = true;
                }
            }
            $color = $group['color'] ?? '#64748b';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#64748b';
            }
            $groups[] = [
                'id'       => $id,
                'name'     => mb_substr(sanitize_text_field($group['name'] ?? $id), 0, 60),
                'color'    => $color,
                'statuses' => $statuses,
            ];
            $groupIds[$id] = true;
        }

        $links = [];
        $seen = [];
        foreach ((array) ($flow['links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $from = sanitize_key($link['from'] ?? '');
            $to = sanitize_key($link['to'] ?? '');
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }
            if (!isset($groupIds[$from]) || !isset($groupIds[$to])) {
                continue;
            }
            $key = $from . '>' . $to;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $links[] = ['from' => $from, 'to' => $to];
        }

        return ['groups' => $groups, 'links' => $links];
    }

    /**
     * Nhóm chứa trạng thái (null nếu chưa phân nhóm).
     */
    public static function groupOfStatus(string $status, ?array $flow = null): ?string
    {
        $flow = $flow ?? self::getFlow();
        foreach ($flow['groups'] as $group) {
            if (in_array($status, $group['statuses'], true)) {
                return $group['id'];
            }
        }

        return null;
    }

    /**
     * Cho phép chuyển trạng thái $from → $to theo flow?
     * Trạng thái chưa phân nhóm / cùng nhóm → theo bảng transition legacy.
     */
    public static function canMove(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $flow = self::getFlow();
        $fromGroup = self::groupOfStatus($from, $flow);
        $toGroup = self::groupOfStatus($to, $flow);

        // Chưa phân nhóm → fallback bảng transition cũ.
        if ($fromGroup === null || $toGroup === null) {
            return in_array($to, Order::getAllowedTransitions()[$from] ?? [], true);
        }

        if ($fromGroup === $toGroup) {
            return in_array($to, Order::getAllowedTransitions()[$from] ?? [], true);
        }

        foreach ($flow['links'] as $link) {
            if ($link['from'] === $fromGroup && $link['to'] === $toGroup) {
                return true;
            }
        }

        return false;
    }

    /**
     * Danh sách trạng thái được phép chuyển tiếp từ $status (dùng cho UI).
     */
    public static function allowedNextStatuses(string $status): array
    {
        $flow = self::getFlow();
        $group = self::groupOfStatus($status, $flow);

        if ($group === null) {
            return Order::getAllowedTransitions()[$status] ?? [];
        }

        $targets = [];
        foreach ($flow['links'] as $link) {
            if ($link['from'] !== $group) {
                continue;
            }
            foreach ($flow['groups'] as $target) {
                if ($target['id'] === $link['to']) {
                    $targets = array_merge($targets, $target['statuses']);
                }
            }
        }

        // Chuyển trong cùng nhóm (nếu legacy cho phép).
        foreach (Order::getAllowedTransitions()[$status] ?? [] as $next) {
            if (self::groupOfStatus($next, $flow) === $group) {
                $targets[] = $next;
            }
        }

        $targets = array_values(array_unique($targets));

        return array_values(array_diff($targets, [$status]));
    }

    /**
     * Đăng ký REST route lưu/đọc flow (permission: manage_options).
     */
    public static function registerRoutes(): void
    {
        register_rest_route('jankx/ecommerce/v1', '/order-flow', [
            'methods'             => 'GET,POST',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback'            => function ($request) {
                if ($request->get_method() === 'POST') {
                    $payload = $request->get_json_params();
                    $flow = self::saveFlow(is_array($payload) ? ($payload['flow'] ?? $payload) : []);

                    return new \WP_REST_Response(['success' => true, 'flow' => $flow], 200);
                }

                return new \WP_REST_Response(['success' => true, 'flow' => self::getFlow()], 200);
            },
        ]);
    }
}
