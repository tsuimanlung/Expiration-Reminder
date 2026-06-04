<?php
/**
 * Expiration Reminder - 公共函数
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';

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
                $servicesHtml .= '<li>' . htmlspecialchars($svc) . '</li>';
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
