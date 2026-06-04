<?php
/**
 * Expiration Reminder - API 入口
 * 处理所有前端 AJAX 请求
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/functions.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_dashboard':
            echo json_encode(apiGetDashboard());
            break;
        case 'get_items':
            echo json_encode(apiGetItems());
            break;
        case 'get_item':
            echo json_encode(apiGetItem());
            break;
        case 'save_item':
            echo json_encode(apiSaveItem());
            break;
        case 'delete_item':
            echo json_encode(apiDeleteItem());
            break;
        case 'get_settings':
            echo json_encode(apiGetSettings());
            break;
        case 'save_settings':
            echo json_encode(apiSaveSettings());
            break;
        case 'test_email':
            echo json_encode(apiTestEmail());
            break;
        case 'get_logs':
            echo json_encode(apiGetLogs());
            break;
        case 'send_test_reminder':
            echo json_encode(apiSendTestReminder());
            break;
        default:
            throw new Exception('未知操作: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// =================== API Functions ===================

function apiGetDashboard(): array {
    $db = getDb();
    $now = date('Y-m-d');
    $monthLater = date('Y-m-d', strtotime('+30 days'));

    // 各类型统计
    $stats = $db->query("SELECT type, COUNT(*) as count FROM items WHERE enabled=1 GROUP BY type")->fetchAll();
    $typeMap = ['server'=>'服务器', 'certificate'=>'SSL证书', 'icp'=>'ICP备案', 'birthday'=>'生日', 'other'=>'其他'];
    $statsData = [];
    foreach ($stats as $s) {
        $statsData[$typeMap[$s['type']] ?? $s['type']] = (int)$s['count'];
    }

    // 即将到期（30天内）
    $upcoming = $db->prepare("SELECT * FROM items WHERE enabled=1 AND expiry_date >= ? AND expiry_date <= ? ORDER BY expiry_date ASC LIMIT 20");
    $upcoming->execute([$now, $monthLater]);
    $upcomingList = [];
    foreach ($upcoming->fetchAll() as $row) {
        $row['details'] = json_decode($row['details'] ?? '{}', true) ?: [];
        $row['reminder_days'] = json_decode($row['reminder_days'] ?? '[]', true) ?: [];
        $row['days_left'] = daysLeft($row['expiry_date']);
        $row['type_label'] = typeLabel($row['type']);
        $upcomingList[] = $row;
    }

    // 已过期
    $expired = $db->prepare("SELECT COUNT(*) as cnt FROM items WHERE enabled=1 AND expiry_date < ?");
    $expired->execute([$now]);
    $expiredCount = (int)$expired->fetch()['cnt'];

    // 总数量
    $total = $db->query("SELECT COUNT(*) as cnt FROM items WHERE enabled=1")->fetch();
    $totalCount = (int)$total['cnt'];

    // 近期已发送提醒
    $recentLogs = $db->query("
        SELECT el.*, i.name as item_name, i.type as item_type
        FROM email_logs el
        LEFT JOIN items i ON el.item_id = i.id
        ORDER BY el.created_at DESC LIMIT 10
    ")->fetchAll();

    return [
        'success' => true,
        'data' => [
            'stats' => $statsData,
            'upcoming' => $upcomingList,
            'expired_count' => $expiredCount,
            'total_count' => $totalCount,
            'recent_logs' => $recentLogs,
        ]
    ];
}

function apiGetItems(): array {
    $db = getDb();
    $type = $_GET['type'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $where = '';
    $params = [];
    if ($type && in_array($type, ['server','certificate','icp','birthday','other'])) {
        $where = 'WHERE type = ?';
        $params[] = $type;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM items {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT * FROM items {$where} ORDER BY expiry_date ASC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['details'] = json_decode($row['details'] ?? '{}', true) ?: [];
        $row['reminder_days'] = json_decode($row['reminder_days'] ?? '[]', true) ?: [];
        $row['days_left'] = daysLeft($row['expiry_date']);
        $row['type_label'] = typeLabel($row['type']);
        $items[] = $row;
    }

    return [
        'success' => true,
        'data' => [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ]
    ];
}

function apiGetItem(): array {
    $db = getDb();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new Exception('无效的ID');

    $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) throw new Exception('记录不存在');

    $item['details'] = json_decode($item['details'] ?? '{}', true) ?: [];
    $item['reminder_days'] = json_decode($item['reminder_days'] ?? '[]', true) ?: [];
    $item['days_left'] = daysLeft($item['expiry_date']);
    $item['type_label'] = typeLabel($item['type']);

    return ['success' => true, 'data' => $item];
}

function apiSaveItem(): array {
    $db = getDb();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('无效的请求数据');

    $id = (int)($input['id'] ?? 0);
    $type = $input['type'] ?? '';
    $name = trim($input['name'] ?? '');
    $expiryDate = $input['expiry_date'] ?? '';
    $details = $input['details'] ?? [];
    $reminderDays = $input['reminder_days'] ?? [30, 15, 7, 1];
    $notes = trim($input['notes'] ?? '');
    $notifyEmail = isset($input['notify_email']) ? (int)$input['notify_email'] : 1;

    if (!in_array($type, ['server','certificate','icp','birthday','other'])) {
        throw new Exception('无效的类型');
    }
    if (!$name) throw new Exception('名称不能为空');
    if (!$expiryDate) throw new Exception('到期日期不能为空');

    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
    $reminderDaysJson = json_encode(array_map('intval', $reminderDays), JSON_UNESCAPED_UNICODE);

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE items SET type=?, name=?, details=?, expiry_date=?, reminder_days=?, notify_email=?, notes=?, updated_at=datetime('now','localtime') WHERE id=?");
        $stmt->execute([$type, $name, $detailsJson, $expiryDate, $reminderDaysJson, $notifyEmail, $notes, $id]);
        if ($stmt->rowCount() === 0) throw new Exception('记录不存在或无需修改');
    } else {
        $stmt = $db->prepare("INSERT INTO items (type, name, details, expiry_date, reminder_days, notify_email, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$type, $name, $detailsJson, $expiryDate, $reminderDaysJson, $notifyEmail, $notes]);
        $id = (int)$db->lastInsertId();
    }

    return ['success' => true, 'data' => ['id' => $id]];
}

function apiDeleteItem(): array {
    $db = getDb();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) throw new Exception('无效的ID');

    $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$id]);

    return ['success' => true, 'message' => '已删除'];
}

function apiGetSettings(): array {
    $db = getDb();
    $rows = $db->query("SELECT key_name, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['key_name']] = $row['value'];
    }

    // 默认值
    $defaults = [
        'smtp_host' => 'smtp.qq.com',
        'smtp_port' => '465',
        'smtp_user' => '10361011@qq.com',
        'smtp_pass' => '',
        'notify_email' => '10361011@qq.com',
        'cron_enabled' => '1',
        'reminder_defaults' => json_encode([30, 15, 7, 1]),
    ];

    foreach ($defaults as $k => $v) {
        if (!isset($settings[$k])) {
            $settings[$k] = $v;
        }
    }

    return ['success' => true, 'data' => $settings];
}

function apiSaveSettings(): array {
    $db = getDb();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('无效的请求数据');

    $allowedKeys = ['smtp_host','smtp_port','smtp_user','smtp_pass','notify_email','cron_enabled','reminder_defaults'];

    foreach ($input as $key => $value) {
        if (in_array($key, $allowedKeys)) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?,?)");
            $stmt->execute([$key, trim((string)$value)]);
        }
    }

    return ['success' => true, 'message' => '设置已保存'];
}

function apiTestEmail(): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $host = $input['smtp_host'] ?? 'smtp.qq.com';
    $port = (int)($input['smtp_port'] ?? 465);
    $user = $input['smtp_user'] ?? '';
    $pass = $input['smtp_pass'] ?? '';
    $to = $input['notify_email'] ?? $user;

    if (!$user || !$pass) {
        throw new Exception('请填写邮箱和授权码');
    }

    $mailer = new SmtpMailer($host, $port, $user, $pass, $user, '到期提醒系统');
    $subject = '【到期提醒】测试邮件 - ' . date('Y-m-d H:i:s');
    $body = "<h2>✅ 测试邮件发送成功</h2>
             <p>如果您收到这封邮件，说明邮件配置正常。</p>
             <p>发送时间：" . date('Y-m-d H:i:s') . "</p>
             <p>您的到期提醒系统可以正常发送通知邮件了！</p>
             <hr>
             <p style='color:#888;font-size:12px;'>Expiration Reminder - 到期提醒系统</p>";

    if ($mailer->send($to, $subject, $body)) {
        return ['success' => true, 'message' => '测试邮件发送成功！请检查收件箱（注意查看垃圾邮件）'];
    } else {
        throw new Exception('发送失败: ' . $mailer->getLastError());
    }
}

function apiGetLogs(): array {
    $db = getDb();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $total = $db->query("SELECT COUNT(*) as cnt FROM email_logs")->fetch()['cnt'];
    $logs = $db->prepare("
        SELECT el.*, i.name as item_name, i.type as item_type
        FROM email_logs el
        LEFT JOIN items i ON el.item_id = i.id
        ORDER BY el.created_at DESC LIMIT ? OFFSET ?
    ");
    $logs->execute([$limit, $offset]);

    return [
        'success' => true,
        'data' => [
            'logs' => $logs->fetchAll(),
            'total' => (int)$total,
        ]
    ];
}

function apiSendTestReminder(): array {
    $db = getDb();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) throw new Exception('无效的ID');

    $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) throw new Exception('记录不存在');

    sendReminderEmail($item, '手动测试提醒');

    return ['success' => true, 'message' => '测试提醒已发送'];
}

