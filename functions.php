<?php
/**
 * Expiration Reminder - 公共函数
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';

// =================== 农历/公历转换 ===================

/**
 * 调用 Python 脚本进行农历→公历转换
 * 依赖 Python 3 + lunarcalendar 库（pip install lunarcalendar）
 */
function lunarToSolar(int $year, int $month, int $day): ?string {
    $script = __DIR__ . '/lunar.py';
    if (!file_exists($script)) return null;

    $pythonCmds = [
        'python3', 'python',
        '/usr/bin/python3', '/usr/bin/python',
    ];

    // Windows 补充路径
    if (DIRECTORY_SEPARATOR === '\\') {
        $localAppData = getenv('LOCALAPPDATA');
        if ($localAppData) {
            $pythonCmds[] = $localAppData . '\\Microsoft\\WindowsApps\\python3.exe';
            $pythonCmds[] = $localAppData . '\\Microsoft\\WindowsApps\\python.exe';
        }
    }

    foreach ($pythonCmds as $py) {
        if (DIRECTORY_SEPARATOR === '\\' && file_exists($py)) {
            $output = @shell_exec(sprintf('"%s" %s %d %d %d 2>nul', $py, escapeshellarg($script), $year, $month, $day));
        } else {
            $output = @shell_exec(sprintf('%s %s %d %d %d 2>/dev/null', escapeshellarg($py), escapeshellarg($script), $year, $month, $day));
        }
        if ($output) {
            $data = json_decode($output, true);
            if ($data && !empty($data['success']) && !empty($data['solar_date'])) {
                return $data['solar_date'];
            }
        }
    }
    return null;
}

/**
 * 计算下一个生日提醒日期
 */
function getNextBirthday(string $birthDate, bool $isLunar = false): string {
    $today = new DateTime('today');
    $cy = (int)$today->format('Y');

    if ($isLunar) {
        $parts = explode('-', $birthDate);
        $lm = (int)$parts[0];
        $ld = (int)$parts[1];
        foreach ([$cy, $cy + 1] as $y) {
            $s = lunarToSolar($y, $lm, $ld);
            if ($s && $s >= $today->format('Y-m-d')) return $s;
        }
        $s = lunarToSolar($cy + 1, $lm, $ld);
        return $s ?: date('Y-m-d', strtotime('+1 year'));
    }

    $md = count(explode('-', $birthDate)) === 3 ? substr($birthDate, 5) : $birthDate;
    $candidate = $cy . '-' . $md;
    if ($candidate >= $today->format('Y-m-d')) return $candidate;
    return ($cy + 1) . '-' . $md;
}

/**
 * 计算下一个重复提醒日期
 */
function getNextRepeatDate(string $repeat, string $expiryDate): string {
    if ($repeat === 'none') return $expiryDate;
    $today = new DateTime('today');
    $cy = (int)$today->format('Y');

    if ($repeat === 'yearly') {
        $md = count(explode('-', $expiryDate)) === 3 ? substr($expiryDate, 5) : $expiryDate;
        $candidate = $cy . '-' . $md;
        if ($candidate >= $today->format('Y-m-d')) return $candidate;
        return ($cy + 1) . '-' . $md;
    }

    if ($repeat === 'monthly') {
        $parts = explode('-', $expiryDate);
        $day = (int)(count($parts) === 3 ? $parts[2] : $parts[0]);
        $maxDay = (int)$today->format('t');
        $targetDay = min($day, $maxDay);
        $thisMonth = sprintf('%s-%02d-%02d', $cy, (int)$today->format('m'), $targetDay);
        if ($thisMonth >= $today->format('Y-m-d')) return $thisMonth;
        $next = new DateTime('first day of next month');
        $maxNext = (int)$next->format('t');
        return $next->format('Y-m') . '-' . sprintf('%02d', min($day, $maxNext));
    }

    if ($repeat === 'weekly') {
        $targetDow = (int)date('w', strtotime($expiryDate));
        $todayDow = (int)$today->format('w');
        $diff = $targetDow - $todayDow;
        if ($diff > 0) return $today->modify("+{$diff} days")->format('Y-m-d');
        if ($diff < 0) return $today->modify("+" . ($diff + 7) . " days")->format('Y-m-d');
        return $today->format('Y-m-d');
    }

    return $expiryDate;
}

// =================== 认证相关 ===================

function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isAuthenticated(): bool {
    initSession();
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
}

function getWebPassword(): string {
    $db = getDb();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute(['web_password']);
    $row = $stmt->fetch();
    return $row ? $row['value'] : '';
}

function getWebUsername(): string {
    $db = getDb();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute(['web_username']);
    $row = $stmt->fetch();
    return $row ? $row['value'] : '';
}

/**
 * 检查是否需要设置密码（首次使用）
 */
function isPasswordSet(): bool {
    return getWebPassword() !== '';
}

// =================== Helper Functions ===================

function daysLeft(string $date): int {
    $now = new DateTime('today');
    $target = new DateTime($date);
    return (int)$now->diff($target)->format('%r%a');
}

function typeLabel(string $type): string {
    $map = ['server'=>'服务器', 'certificate'=>'SSL证书', 'icp'=>'ICP备案', 'birthday'=>'生日', 'other'=>'其他'];
    return $map[$type] ?? $type;
}

/**
 * 发送提醒邮件
 */
function sendReminderEmail(array $item, string $triggerReason = ''): bool {
    $db = getDb();

    // 获取设置
    $settings = [];
    $rows = $db->query("SELECT key_name, value FROM settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['key_name']] = $row['value'];
    }

    $host = $settings['smtp_host'] ?? 'smtp.qq.com';
    $port = (int)($settings['smtp_port'] ?? 465);
    $user = $settings['smtp_user'] ?? '';
    $pass = $settings['smtp_pass'] ?? '';
    $notifyEmail = $settings['notify_email'] ?? $user;

    if (!$user || !$pass || !$notifyEmail) {
        error_log('[Reminder] 邮件配置不完整，无法发送提醒');
        return false;
    }

    $days = daysLeft($item['expiry_date']);
    $details = json_decode($item['details'] ?? '{}', true) ?: [];
    $typeLabelStr = typeLabel($item['type']);

    // 构建邮件正文
    $subject = "【到期提醒】{$typeLabelStr}: {$item['name']} 即将到期";

    $servicesHtml = '';
    if ($item['type'] === 'server' && !empty($details['services'])) {
        $services = is_array($details['services']) ? $details['services'] : [];
        if (count($services) > 0) {
            $servicesHtml = '<p><strong>📦 提供的服务：</strong></p><ul>';
            foreach ($services as $svc) {
                $svcName = is_string($svc) ? $svc : ($svc['name'] ?? '');
                $svcUrl = is_string($svc) ? '' : ($svc['url'] ?? '');
                $display = $svcName;
                if ($svcUrl) $display .= ' (' . htmlspecialchars($svcUrl) . ')';
                $servicesHtml .= '<li>' . htmlspecialchars($display) . '</li>';
            }
            $servicesHtml .= '</ul>';
        }
    }

    $extraInfo = '';
    if ($item['type'] === 'server' && !empty($details['ip'])) {
        $extraInfo .= '<p><strong>IP地址：</strong>' . htmlspecialchars($details['ip']) . '</p>';
    }
    if ($item['type'] === 'server' && !empty($details['provider'])) {
        $extraInfo .= '<p><strong>服务商：</strong>' . htmlspecialchars($details['provider']) . '</p>';
    }
    if (in_array($item['type'], ['certificate','icp']) && !empty($details['domain'])) {
        $extraInfo .= '<p><strong>域名：</strong>' . htmlspecialchars($details['domain']) . '</p>';
    }
    if ($item['notes']) {
        $extraInfo .= '<p><strong>备注：</strong>' . nl2br(htmlspecialchars($item['notes'])) . '</p>';
    }

    $body = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; max-width:600px; margin:0 auto; padding:30px; background:linear-gradient(135deg, #0a1628, #1a2a4a); color:#e0e8f0; border-radius:16px;'>
        <div style='text-align:center; margin-bottom:30px;'>
            <div style='font-size:48px; margin-bottom:10px;'>⏰</div>
            <h1 style='color:#4fc3f7; margin:0; font-size:24px;'>到期提醒通知</h1>
        </div>
        <div style='background:rgba(255,255,255,0.08); backdrop-filter:blur(10px); border-radius:12px; padding:24px; border:1px solid rgba(79,195,247,0.2);'>
            <p style='font-size:18px; color:#fff; margin-top:0;'><strong>{$typeLabelStr}: {$item['name']}</strong></p>
            <div style='background:rgba(255,255,255,0.05); border-radius:8px; padding:16px; margin:16px 0;'>
                <p><strong>📅 到期时间：</strong>" . date('Y年m月d日', strtotime($item['expiry_date'])) . "</p>
                <p><strong>⏳ 剩余天数：</strong>" . ($days > 0 ? "<span style='color:#ff9800;font-size:20px;font-weight:bold;'>{$days}天</span>" : "<span style='color:#f44336;font-size:20px;font-weight:bold;'>已过期</span>") . "</p>
            </div>
            {$servicesHtml}
            {$extraInfo}
            " . ($triggerReason ? "<p style='color:#888;font-size:12px;'>触发方式：{$triggerReason}</p>" : '') . "
        </div>
        <hr style='border:none;border-top:1px solid rgba(255,255,255,0.1);margin:20px 0;'>
        <p style='color:#888;font-size:12px;text-align:center;'>此邮件由 Expiration Reminder 到期提醒系统自动发送<br>请及时处理相关续费或更新事宜</p>
    </div>";

    $mailer = new SmtpMailer($host, $port, $user, $pass, $user, '到期提醒系统');
    return $mailer->send($notifyEmail, $subject, $body);
}
