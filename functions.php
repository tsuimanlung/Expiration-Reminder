<?php
/**
 * Expiration Reminder - 公共函数
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';

// =================== 农历/公历转换 ===================

// 农历数据表（1901-2100）
// 每项编码: 0-3位=闰月, 4-15位=12个月天数(0=29天,1=30天), 16-19位=闰月天数
define('LUNAR_INFO', [
    0x04bd8,0x04ae0,0x0a570,0x054d5,0x0d260,0x0d950,0x16554,0x056a0,0x09ad0,0x055d2,
    0x04ae0,0x0a5b6,0x0a4d0,0x0d250,0x1d255,0x0b540,0x0d6a0,0x0ada2,0x095b0,0x14977,
    0x04970,0x0a4b0,0x0b4b5,0x06a50,0x06d40,0x1ab54,0x02b60,0x09570,0x052f2,0x04970,
    0x06566,0x0d4a0,0x0ea50,0x06e95,0x05ad0,0x02b60,0x186e3,0x092e0,0x1c8d7,0x0c950,
    0x0d4a0,0x1d8a6,0x0b550,0x056a0,0x1a5b4,0x025d0,0x092d0,0x0d2b2,0x0a950,0x0b557,
    0x06ca0,0x0b550,0x15355,0x04da0,0x0a5b0,0x14573,0x052b0,0x0a9a8,0x0e950,0x06aa0,
    0x0aea6,0x0ab50,0x04b60,0x0aae4,0x0a570,0x05260,0x0f263,0x0d950,0x05b57,0x056a0,
    0x096d0,0x04dd5,0x04ad0,0x0a4d0,0x0d4d4,0x0d250,0x0d558,0x0b540,0x0b6a0,0x195a6,
    0x095b0,0x049b0,0x0a974,0x0a4b0,0x0b27a,0x06a50,0x06d40,0x0af46,0x0ab60,0x09570,
    0x04af5,0x04970,0x064b0,0x074a3,0x0ea50,0x06b58,0x05ac0,0x0ab60,0x096d5,0x092e0,
    0x0c960,0x0d954,0x0d4a0,0x0da50,0x07552,0x056a0,0x0abb7,0x025d0,0x092d0,0x0cab5,
    0x0a950,0x0b4a0,0x0baa4,0x0ad50,0x055d9,0x04ba0,0x0a5b0,0x15176,0x052b0,0x0a930,
    0x07954,0x06aa0,0x0ad50,0x05b52,0x04b60,0x0a6e6,0x0a4e0,0x0d260,0x0ea65,0x0d530,
    0x05aa0,0x076a3,0x096d0,0x04afb,0x04ad0,0x0a4d0,0x1d0b6,0x0d250,0x0d520,0x0dd45,
    0x0b5a0,0x056d0,0x055b2,0x049b0,0x0a577,0x0a4b0,0x0aa50,0x1b255,0x06d20,0x0ada0,
    0x14b63,0x09370,0x049f8,0x04970,0x064b0,0x168a6,0x0ea50,0x06aa0,0x1a6c4,0x0aae0,
    0x092e0,0x0d2e3,0x0c960,0x0d557,0x0d4a0,0x0da50,0x05d55,0x056a0,0x0a6d0,0x055d4,
    0x052d0,0x0a9b8,0x0a950,0x0b4a0,0x0b6a6,0x0ad50,0x055a0,0x0aba4,0x0a5b0,0x052b0,
    0x0b273,0x06930,0x07337,0x06aa0,0x0ad50,0x14b55,0x04b60,0x0a570,0x054e4,0x0d160,
    0x0e968,0x0d520,0x0daa0,0x16aa6,0x056d0,0x04ae0,0x0a9d4,0x0a4d0,0x0d150,0x0f252,
]);

/**
 * 农历转公历
 */
function lunarToSolar(int $year, int $month, int $day): ?string {
    $idx = $year - 1901;
    if ($idx < 0 || $idx >= count(LUNAR_INFO)) return null;

    $leap = LUNAR_INFO[$idx] & 0xf;
    $leapDays = (LUNAR_INFO[$idx] >> 16) & 0x1 ? 30 : 29;

    // 计算该年正月初一
    $springData = getSpringFestival($year);
    if (!$springData) return null;

    $base = strtotime($springData);
    $offset = 0;

    // 累加各月天数到目标月
    for ($m = 1; $m < $month; $m++) {
        $offset += (LUNAR_INFO[$idx] >> (4 + $m - 1)) & 1 ? 30 : 29;
    }

    // 如果有闰月且在目标月之前
    if ($leap > 0 && $month > $leap) {
        $offset += $leapDays;
    }

    $offset += $day - 1;
    $ts = $base + $offset * 86400;

    // 检查是否超出当前表范围（超过2100年需继续推算）
    $maxTs = strtotime('2101-01-31'); // 近似值
    if ($ts > $maxTs) return null;

    return date('Y-m-d', $ts);
}

/**
 * 获取农历新年（正月初一）公历日期查表
 */
function getSpringFestival(int $year): ?string {
    $map = [
        1901=>'1901-02-19',1902=>'1902-02-08',1903=>'1903-01-29',1904=>'1904-02-16',1905=>'1905-02-04',
        1906=>'1906-01-25',1907=>'1907-02-13',1908=>'1908-02-02',1909=>'1909-01-22',1910=>'1910-02-10',
        1911=>'1911-01-30',1912=>'1912-02-18',1913=>'1913-02-06',1914=>'1914-01-26',1915=>'1915-02-14',
        1916=>'1916-02-03',1917=>'1917-01-23',1918=>'1918-02-11',1919=>'1919-02-01',1920=>'1920-02-20',
        1921=>'1921-02-08',1922=>'1922-01-28',1923=>'1923-02-16',1924=>'1924-02-05',1925=>'1925-01-24',
        1926=>'1926-02-13',1927=>'1927-02-02',1928=>'1928-01-23',1929=>'1929-02-10',1930=>'1930-01-30',
        1931=>'1931-02-17',1932=>'1932-02-06',1933=>'1933-01-26',1934=>'1934-02-14',1935=>'1935-02-04',
        1936=>'1936-01-24',1937=>'1937-02-11',1938=>'1938-01-31',1939=>'1939-02-19',1940=>'1940-02-08',
        1941=>'1941-01-27',1942=>'1942-02-15',1943=>'1943-02-05',1944=>'1944-01-25',1945=>'1945-02-13',
        1946=>'1946-02-02',1947=>'1947-01-22',1948=>'1948-02-10',1949=>'1949-01-29',1950=>'1950-02-17',
        1951=>'1951-02-06',1952=>'1952-01-27',1953=>'1953-02-14',1954=>'1954-02-03',1955=>'1955-01-24',
        1956=>'1956-02-12',1957=>'1957-01-31',1958=>'1958-02-18',1959=>'1959-02-08',1960=>'1960-01-28',
        1961=>'1961-02-15',1962=>'1962-02-05',1963=>'1963-01-25',1964=>'1964-02-13',1965=>'1965-02-02',
        1966=>'1966-01-21',1967=>'1967-02-09',1968=>'1968-01-30',1969=>'1969-02-17',1970=>'1970-02-06',
        1971=>'1971-01-27',1972=>'1972-02-15',1973=>'1973-02-03',1974=>'1974-01-23',1975=>'1975-02-11',
        1976=>'1976-01-31',1977=>'1977-02-18',1978=>'1978-02-07',1979=>'1979-01-28',1980=>'1980-02-16',
        1981=>'1981-02-05',1982=>'1982-01-25',1983=>'1983-02-13',1984=>'1984-02-02',1985=>'1985-02-20',
        1986=>'1986-02-09',1987=>'1987-01-29',1988=>'1988-02-17',1989=>'1989-02-06',1990=>'1990-01-27',
        1991=>'1991-02-15',1992=>'1992-02-04',1993=>'1993-01-23',1994=>'1994-02-10',1995=>'1995-01-31',
        1996=>'1996-02-19',1997=>'1997-02-07',1998=>'1998-01-28',1999=>'1999-02-16',2000=>'2000-02-05',
        2001=>'2001-01-24',2002=>'2002-02-12',2003=>'2003-02-01',2004=>'2004-01-22',2005=>'2005-02-09',
        2006=>'2006-01-29',2007=>'2007-02-18',2008=>'2008-02-07',2009=>'2009-01-26',2010=>'2010-02-14',
        2011=>'2011-02-03',2012=>'2012-01-23',2013=>'2013-02-10',2014=>'2014-01-31',2015=>'2015-02-19',
        2016=>'2016-02-08',2017=>'2017-01-28',2018=>'2018-02-16',2019=>'2019-02-05',2020=>'2020-01-25',
        2021=>'2021-02-12',2022=>'2022-02-01',2023=>'2023-01-22',2024=>'2024-02-10',2025=>'2025-01-29',
        2026=>'2026-02-17',2027=>'2027-02-06',2028=>'2028-01-26',2029=>'2029-02-13',2030=>'2030-02-03',
        2031=>'2031-01-23',2032=>'2032-02-11',2033=>'2033-01-31',2034=>'2034-02-19',2035=>'2035-02-08',
        2036=>'2036-01-28',2037=>'2037-02-15',2038=>'2038-02-04',2039=>'2039-01-24',2040=>'2040-02-12',
        2041=>'2041-02-01',2042=>'2042-02-20',2043=>'2043-02-10',2044=>'2044-01-30',2045=>'2045-02-17',
        2046=>'2046-02-06',2047=>'2047-01-26',2048=>'2048-02-14',2049=>'2049-02-02',2050=>'2050-01-23',
        2051=>'2051-02-11',2052=>'2052-01-31',2053=>'2053-02-18',2054=>'2054-02-08',2055=>'2055-01-28',
        2056=>'2056-02-15',2057=>'2057-02-04',2058=>'2058-01-24',2059=>'2059-02-12',2060=>'2060-02-02',
        2061=>'2061-01-21',2062=>'2062-02-09',2063=>'2063-01-29',2064=>'2064-02-17',2065=>'2065-02-05',
        2066=>'2066-01-26',2067=>'2067-02-14',2068=>'2068-02-03',2069=>'2069-01-23',2070=>'2070-02-11',
        2071=>'2071-01-31',2072=>'2072-02-19',2073=>'2073-02-07',2074=>'2074-01-27',2075=>'2075-02-15',
        2076=>'2076-02-05',2077=>'2077-01-24',2078=>'2078-02-12',2079=>'2079-02-02',2080=>'2080-01-22',
        2081=>'2081-02-09',2082=>'2082-01-29',2083=>'2083-02-17',2084=>'2084-02-06',2085=>'2085-01-26',
        2086=>'2086-02-14',2087=>'2087-02-03',2088=>'2088-01-24',2089=>'2089-02-10',2090=>'2090-01-30',
        2091=>'2091-02-18',2092=>'2092-02-07',2093=>'2093-01-27',2094=>'2094-02-15',2095=>'2095-02-05',
        2096=>'2096-01-25',2097=>'2097-02-12',2098=>'2098-02-01',2099=>'2099-01-21',2100=>'2100-02-09',
    ];
    return $map[$year] ?? null;
}

/**
 * 计算下一个生日提醒日期
 * @param string $birthDate 生日（公历YYYY-MM-DD或MM-DD，农历MM-DD）
 * @param bool $isLunar 是否为农历
 * @return string 下一次生日提醒日期 YYYY-MM-DD
 */
function getNextBirthday(string $birthDate, bool $isLunar = false): string {
    $today = new DateTime('today');
    $cy = (int)$today->format('Y');

    if ($isLunar) {
        $parts = explode('-', $birthDate);
        $lm = (int)$parts[0];
        $ld = (int)$parts[1];
        // 先试今年，再试明年
        foreach ([$cy, $cy + 1] as $y) {
            $s = lunarToSolar($y, $lm, $ld);
            if ($s && $s >= $today->format('Y-m-d')) return $s;
        }
        return date('Y-m-d', strtotime('+1 year'));
    }

    $md = count(explode('-', $birthDate)) === 3 ? substr($birthDate, 5) : $birthDate;
    $candidate = $cy . '-' . $md;
    if ($candidate >= $today->format('Y-m-d')) return $candidate;
    return ($cy + 1) . '-' . $md;
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
