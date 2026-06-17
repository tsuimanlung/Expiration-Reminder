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

// 初始化会话
initSession();

$action = $_REQUEST['action'] ?? '';

try {
    // 公开接口（不需要登录）
    $publicActions = ['login', 'check_auth', 'setup_password'];

    if (!in_array($action, $publicActions)) {
        requireAuth();
    }

    switch ($action) {
        case 'check_auth':
            echo json_encode(apiCheckAuth());
            break;
        case 'login':
            echo json_encode(apiLogin());
            break;
        case 'logout':
            echo json_encode(apiLogout());
            break;
        case 'setup_password':
            echo json_encode(apiSetupPassword());
            break;
        case 'change_password':
            echo json_encode(apiChangePassword());
            break;
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

// =================== 认证 API ===================

function apiCheckAuth(): array {
    $passwordSet = isPasswordSet();
    return [
        'success' => true,
        'data' => [
            'authenticated' => isAuthenticated(),
            'password_set' => $passwordSet,
        ]
    ];
}

function apiLogin(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('无效的请求数据');

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        throw new Exception('请输入账号和密码');
    }

    $storedHash = getWebPassword();
    $storedUser = getWebUsername();

    if (!$storedHash) {
        throw new Exception('尚未设置登录密码，请先完成初始设置');
    }

    if ($username !== $storedUser) {
        throw new Exception('账号或密码错误');
    }

    if (!password_verify($password, $storedHash)) {
        throw new Exception('账号或密码错误');
    }

    initSession();
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    session_regenerate_id(true);

    return ['success' => true, 'message' => '登录成功'];
}

function apiLogout(): array {
    initSession();
    $_SESSION = [];
    session_destroy();
    return ['success' => true, 'message' => '已退出登录'];
}

/**
 * 首次设置密码
 */
function apiSetupPassword(): array {
    if (isPasswordSet()) {
        throw new Exception('密码已设置，不能重复初始化');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('无效的请求数据');

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username) throw new Exception('请输入账号');
    if (strlen($username) < 2) throw new Exception('账号至少2个字符');
    if (!$password) throw new Exception('请输入密码');
    if (strlen($password) < 4) throw new Exception('密码至少4个字符');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db = getDb();

    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?,?)");
    $stmt->execute(['web_username', $username]);

    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?,?)");
    $stmt->execute(['web_password', $hash]);

    // 自动登录
    initSession();
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    session_regenerate_id(true);

    return ['success' => true, 'message' => '设置成功，已自动登录'];
}

/**
 * 修改密码（需要当前密码验证）
 */
function apiChangePassword(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('无效的请求数据');

    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $newUsername = trim($input['new_username'] ?? '');

    if (!$currentPassword) throw new Exception('请输入当前密码');
    if (!$newPassword) throw new Exception('请输入新密码');
    if (strlen($newPassword) < 4) throw new Exception('新密码至少4个字符');

    $storedHash = getWebPassword();
    if (!password_verify($currentPassword, $storedHash)) {
        throw new Exception('当前密码错误');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db = getDb();

    if ($newUsername) {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?,?)");
        $stmt->execute(['web_username', $newUsername]);
        $_SESSION['username'] = $newUsername;
    }

    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?,?)");
    $stmt->execute(['web_password', $newHash]);

    return ['success' => true, 'message' => '密码已修改'];
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

    // 即将到期（30天内 + 已过期）
    $upcoming = $db->prepare("SELECT * FROM items WHERE enabled=1 AND expiry_date <= ? ORDER BY expiry_date ASC LIMIT 20");
    $upcoming->execute([$monthLater]);
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

    // 生日类型：自动计算下一次生日日期
    if ($type === 'birthday') {
        $isLunar = !empty($details['is_lunar']);
        $expiryDate = getNextBirthday($expiryDate, $isLunar);
    }

    // 其他提醒：支持重复类型（每年/每月/每周）
    $repeat = $details['repeat'] ?? 'none';
    if ($type === 'other' && in_array($repeat, ['yearly','monthly','weekly'])) {
        $expiryDate = getNextRepeatDate($repeat, $expiryDate);
    }

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

