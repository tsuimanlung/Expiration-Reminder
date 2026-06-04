<?php
/**
 * SMTP 邮件发送类
 * 支持 QQ邮箱、163邮箱等主流SMTP服务
 * Expiration Reminder - 到期提醒系统
 */

class SmtpMailer {
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;
    private bool $ssl;
    private $socket = null;
    private string $lastError = '';
    private bool $debug = false;

    public function __construct(string $host, int $port, string $user, string $pass, string $from = '', string $fromName = '到期提醒系统', bool $ssl = true) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->from = $from ?: $user;
        $this->fromName = $fromName;
        $this->ssl = $ssl;
    }

    /**
     * 发送邮件
     * @param string|array $to 收件人（逗号分隔或数组）
     * @param string $subject 主题
     * @param string $body HTML正文
     * @return bool
     */
    public function send($to, string $subject, string $body): bool {
        $this->lastError = '';
        $toArr = is_array($to) ? $to : array_map('trim', explode(',', $to));
        $toList = implode(', ', $toArr);

        try {
            $this->connect();
            $this->auth();
            $this->sendMail($toList, $subject, $body);
            $this->disconnect();
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function setDebug(bool $d): void {
        $this->debug = $d;
    }

    private function connect(): void {
        $host = $this->ssl ? 'ssl://' . $this->host : $this->host;
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $this->socket = @stream_socket_client(
            $host . ':' . $this->port,
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$this->socket) {
            throw new Exception("连接失败: {$errstr} ({$errno})");
        }

        $this->readResponse(220);

        if (!$this->ssl && extension_loaded('openssl')) {
            $this->sendCommand("EHLO {$this->host}", 250);
            $this->sendCommand("STARTTLS", 220);
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        $this->sendCommand("EHLO {$this->host}", 250);
    }

    private function auth(): void {
        $this->sendCommand("AUTH LOGIN", 334);
        $this->sendCommand(base64_encode($this->user), 334);
        $this->sendCommand(base64_encode($this->pass), 235);
    }

    private function sendMail(string $to, string $subject, string $body): void {
        $this->sendCommand("MAIL FROM:<{$this->from}>", 250);
        $recipients = array_map('trim', explode(',', $to));
        foreach ($recipients as $rcpt) {
            $this->sendCommand("RCPT TO:<{$rcpt}>", 250);
        }
        $this->sendCommand("DATA", 354);

        $headers = [
            "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->from}>",
            "To: {$to}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "Date: " . date('r'),
            "X-Mailer: Expiration-Reminder/1.0",
        ];

        $headerStr = implode("\r\n", $headers) . "\r\n\r\n";
        $fullMsg = $headerStr . chunk_split(base64_encode($body));

        $this->sendCommand($fullMsg, 250, true);
    }

    private function sendCommand(string $cmd, int $expectedCode, bool $isData = false): void {
        if (!$this->socket) {
            throw new Exception("Socket 未连接");
        }

        if ($this->debug) {
            $this->log(">>> {$cmd}");
        }

        if ($isData) {
            fwrite($this->socket, $cmd . "\r\n.\r\n");
        } else {
            fwrite($this->socket, $cmd . "\r\n");
        }

        if (!$isData || $expectedCode === 250) {
            $response = $this->readResponse($expectedCode);
            if ($this->debug) {
                $this->log("<<< {$response}");
            }
        }
    }

    private function readResponse(int $expectedCode): string {
        $response = '';
        while (true) {
            $line = @fgets($this->socket, 512);
            if ($line === false) {
                throw new Exception("读取响应失败");
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP 错误: {$response}");
        }
        return $response;
    }

    private function disconnect(): void {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function log(string $msg): void {
        error_log('[SMTP] ' . $msg);
    }

    public function __destruct() {
        $this->disconnect();
    }
}
