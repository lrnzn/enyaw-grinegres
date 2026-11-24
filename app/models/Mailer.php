<?php
/**
 * Minimal SMTP client (no libraries needed). Works with Gmail (smtp.gmail.com, port 587 STARTTLS, App Password)
 * and any other SMTP server. One connection is kept open per request, so several messages go out quickly.
 */
class Mailer
{
    private static $sock = null;
    private static $key  = null;

    /** Returns [ok, note]. Never throws. */
    public static function send($to, $toName, $subject, $body, $timeout = 6)
    {
        $c = config('notifications.email');
        $to = self::clean($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'The email address is not valid'];
        $from = self::clean($c['from_email'] ?: $c['username']);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return [false, 'No sender email address is set in the configuration'];
        if (empty($c['host'])) return [false, 'No SMTP server is set in the configuration'];
        $fromName = self::clean($c['from_name'] ?: config('school_name'));

        try {
            $s = self::connection($c, max(1, (int)$timeout));
            $domain = substr(strrchr($from, '@'), 1);
            $msg = 'Date: ' . date('r') . "\r\n"
                 . 'From: ' . self::encodeName($fromName) . " <$from>\r\n"
                 . 'To: ' . ($toName !== '' && $toName !== null ? self::encodeName(self::clean($toName)) . " <$to>" : "<$to>") . "\r\n"
                 . 'Subject: ' . self::encodeHeader(self::clean($subject)) . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>\r\n"
                 . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                 . chunk_split(base64_encode((string)$body), 76, "\r\n");   // base64 keeps the body free of lone dots and odd characters
            self::expect($s, "MAIL FROM:<$from>", [250]);
            self::expect($s, "RCPT TO:<$to>", [250, 251]);
            self::expect($s, 'DATA', [354]);
            fwrite($s, $msg . "\r\n.\r\n");
            self::reply($s, [250]);
            return [true, 'Accepted by the email server'];
        } catch (Throwable $e) {
            self::close(false);   // start fresh next time
            return [false, 'Email not sent: ' . substr($e->getMessage(), 0, 200)];
        }
    }

    private static function connection(array $c, $timeout)
    {
        $key = $c['host'] . ':' . $c['port'] . ':' . $c['username'];
        if (self::$sock && self::$key === $key) {
            stream_set_timeout(self::$sock, $timeout);
            try { self::expect(self::$sock, 'RSET', [250]); return self::$sock; } catch (Throwable $e) { self::close(false); }
        }
        $verify = !empty($c['verify_peer']);
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'peer_name' => $c['host']]]);
        $scheme = ($c['encryption'] ?? 'tls') === 'ssl' ? 'ssl' : 'tcp';
        $s = @stream_socket_client("$scheme://{$c['host']}:{$c['port']}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new RuntimeException("cannot reach {$c['host']}:{$c['port']} ($errstr)");
        stream_set_timeout($s, $timeout);
        self::reply($s, [220]);
        $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost');
        self::expect($s, $ehlo, [250]);
        if (($c['encryption'] ?? 'tls') === 'tls') {
            self::expect($s, 'STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('could not start a secure (TLS) connection');
            self::expect($s, $ehlo, [250]);
        }
        if ($c['username'] !== '') {
            self::expect($s, 'AUTH LOGIN', [334]);
            self::expect($s, base64_encode($c['username']), [334]);
            // Google shows App Passwords with spaces; they are not part of the password.
            self::expect($s, base64_encode(str_replace(' ', '', $c['password'])), [235], true);
        }
        self::$sock = $s;
        self::$key  = $key;
        register_shutdown_function([self::class, 'close']);
        return $s;
    }

    public static function close($polite = true)
    {
        if (!self::$sock) return;
        if ($polite) { @fwrite(self::$sock, "QUIT\r\n"); }
        @fclose(self::$sock);
        self::$sock = null;
        self::$key  = null;
    }

    private static function expect($s, $command, array $codes, $secret = false)
    {
        fwrite($s, $command . "\r\n");
        return self::reply($s, $codes, $secret ? 'sign-in refused (check the Gmail address and App Password)' : null);
    }

    /** Read one (possibly multi-line) reply and check its code. */
    private static function reply($s, array $codes, $friendly = null)
    {
        $text = '';
        do {
            $line = fgets($s, 1024);
            if ($line === false) throw new RuntimeException(stream_get_meta_data($s)['timed_out'] ? 'the email server did not answer in time' : 'the connection to the email server was lost');
            $text .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int)substr($text, 0, 3);
        if (!in_array($code, $codes, true)) throw new RuntimeException($friendly ?: ('server said ' . trim(preg_replace('/\s+/', ' ', $text))));
        return $text;
    }

    private static function clean($v) { return trim(str_replace(["\r", "\n", "\0"], ' ', (string)$v)); }

    private static function encodeName($v) { $e = self::encodeHeader($v); return $e === $v ? '"' . addcslashes($v, '"\\') . '"' : $e; }

    private static function encodeHeader($v) { return preg_match('/^[\x20-\x7E]*$/', $v) ? $v : '=?UTF-8?B?' . base64_encode($v) . '?='; }
}
