<?php
/**
 * Expiration Reminder - 定时任务脚本
 *
 * 使用方法（crontab 每天8点执行）：
 * 0 8 * * * /usr/bin/php /path/to/cron.php
 *
 * 也可以通过 Web 访问（需配置安全访问）：
 * https://your-domain.com/cron.php?key=your_secret_key
 */

require_once __DIR__ . '/functions.php';

// 安全校验（可选）
$securityKey = '';
$stmt = getDb()->prepare("SELECT value FROM settings WHERE key_name = ?");
$stmt->execute(['cron_key']);
$row = $stmt->fetch();
if ($row) {
    $securityKey = $row['value'];
}
if ($securityKey && (!isset($_GET['key']) || $_GET['key'] !== $securityKey)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => '无效的访问密钥']));
}

// 仅允许 CLI 或 带 key 的 Web 访问
if (php_sapi_name() !== 'cli' && !$securityKey) {
    die("请设置 cron_key 后再通过 Web 访问，或通过 CLI 运行。");
}

$startTime = microtime(true);
$results = checkAndSendReminders();
$elapsed = round(microtime(true) - $startTime, 2);

$output = [
    'success' => true,
    'time' => date('Y-m-d H:i:s'),
    'elapsed' => $elapsed . 's',
    'results' => $results,
];

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] 到期提醒检查完成\n";
    echo "耗时: {$elapsed}s\n";
    echo "检查项: {$results['checked']} | 已发送: {$results['sent']} | 跳过: {$results['skipped']} | 失败: {$results['failed']}\n";
    if (!empty($results['errors'])) {
        echo "错误:\n";
        foreach ($results['errors'] as $err) {
            echo "  - {$err}\n";
        }
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * 检查所有到期项目并发送提醒
 */
function checkAndSendReminders(): array {
    $db = getDb();
    $today = date('Y-m-d');
    $result = ['checked' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

    // 获取所有启用的到期项目
    $items = $db->query("SELECT * FROM items WHERE enabled = 1")->fetchAll();

    foreach ($items as $item) {
        $result['checked']++;
        $reminderDays = json_decode($item['reminder_days'] ?? '[]', true) ?: [];
        $expiryDate = $item['expiry_date'];
        $daysLeft = daysLeft($expiryDate);

        foreach ($reminderDays as $day) {
            $day = (int)$day;
            // 计算应该在哪天发送提醒
            $reminderDate = date('Y-m-d', strtotime($expiryDate . " -{$day} days"));

            // 如果提醒日期就是今天，或者今天刚好到期，发送提醒
            if ($reminderDate === $today) {
                // 检查是否已经发送过
                $checkStmt = $db->prepare(
                    "SELECT COUNT(*) as cnt FROM email_logs WHERE item_id = ? AND reminder_day = ? AND sent_date = ?"
                );
                $checkStmt->execute([$item['id'], $day, $today]);
                $alreadySent = (int)$checkStmt->fetch()['cnt'];

                if ($alreadySent > 0) {
                    $result['skipped']++;
                    continue;
                }

                // 发送提醒
                try {
                    $success = sendReminderEmail($item, "提前{$day}天提醒");
                    $logStmt = $db->prepare(
                        "INSERT INTO email_logs (item_id, reminder_day, sent_date, status, message) VALUES (?,?,?,?,?)"
                    );
                    if ($success) {
                        $result['sent']++;
                        $logStmt->execute([$item['id'], $day, $today, 'success', '']);
                    } else {
                        $result['failed']++;
                        $logStmt->execute([$item['id'], $day, $today, 'failed', '发送失败']);
                        $result['errors'][] = "{$item['name']} (提前{$day}天): 发送失败";
                    }
                } catch (Exception $e) {
                    $result['failed']++;
                    $logStmt = $db->prepare(
                        "INSERT INTO email_logs (item_id, reminder_day, sent_date, status, message) VALUES (?,?,?,?,?)"
                    );
                    $logStmt->execute([$item['id'], $day, $today, 'failed', $e->getMessage()]);
                    $result['errors'][] = "{$item['name']} (提前{$day}天): " . $e->getMessage();
                }
            }
        }

        // 处理已过期的项目：到期当天也发一次提醒
        if ($daysLeft == 0) {
            $day = 0; // 特殊标记：到期日
            $checkStmt = $db->prepare(
                "SELECT COUNT(*) as cnt FROM email_logs WHERE item_id = ? AND reminder_day = 0 AND sent_date = ?"
            );
            $checkStmt->execute([$item['id'], $today]);
            $alreadySent = (int)$checkStmt->fetch()['cnt'];

            if ($alreadySent === 0) {
                try {
                    $success = sendReminderEmail($item, "到期日提醒");
                    $logStmt = $db->prepare(
                        "INSERT INTO email_logs (item_id, reminder_day, sent_date, status, message) VALUES (?,?,?,?,?)"
                    );
                    if ($success) {
                        $result['sent']++;
                        $logStmt->execute([$item['id'], 0, $today, 'success', '']);
                    } else {
                        $result['failed']++;
                        $logStmt->execute([$item['id'], 0, $today, 'failed', '发送失败']);
                        $result['errors'][] = "{$item['name']} (到期日): 发送失败";
                    }
                } catch (Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = "{$item['name']} (到期日): " . $e->getMessage();
                }
            }
        }

        // 处理生日：自动更新为下一年的日期
        if ($item['type'] === 'birthday' && $daysLeft < 0) {
            $det = json_decode($item['details'] ?? '{}', true) ?: [];
            $isLunar = !empty($det['is_lunar']);
            $bDate = $expiryDate;
            if ($isLunar && !empty($det['lunar_mm']) && !empty($det['lunar_dd'])) {
                $bDate = sprintf('%02d-%02d', (int)$det['lunar_mm'], (int)$det['lunar_dd']);
            }
            $updateStmt = $db->prepare("UPDATE items SET expiry_date = ? WHERE id = ?");
            $updateStmt->execute([getNextBirthday($bDate, $isLunar), $item['id']]);
        }

        // 处理其他提醒的重复类型（每年/每月/每周）
        if ($item['type'] === 'other' && $daysLeft < 0) {
            $det = json_decode($item['details'] ?? '{}', true) ?: [];
            $repeat = $det['repeat'] ?? 'none';
            if (in_array($repeat, ['yearly','monthly','weekly'])) {
                $updateStmt = $db->prepare("UPDATE items SET expiry_date = ? WHERE id = ?");
                $updateStmt->execute([getNextRepeatDate($repeat, $expiryDate), $item['id']]);
            }
        }
    }

    return $result;
}
