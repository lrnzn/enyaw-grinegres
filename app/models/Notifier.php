<?php
/**
 * Sends messages to parents when a child arrives and when a child is picked up.
 *
 * Rules that keep the gate safe:
 *  - messages are created only AFTER the gate action has been saved, so a failed release sends nothing;
 *  - a problem with messaging (no signal, bad key) never blocks or undoes a release;
 *  - only authorized guardians who have not opted out are messaged (a revoked guardian is never told where the child is);
 *  - every message, sent or not, is kept on the Messages page.
 */
class Notifier
{
    /** Philippine mobile number to the local form 09XXXXXXXXX, or null when it is not a valid mobile number. */
    public static function normalizePhone($raw)
    {
        $d = preg_replace('/\D+/', '', (string)$raw);
        if (preg_match('/^63(9\d{9})$/', $d, $m)) return '0' . $m[1];
        if (preg_match('/^09\d{9}$/', $d)) return $d;
        if (preg_match('/^9\d{9}$/', $d)) return '0' . $d;
        return null;
    }

    public static function compose($event, $student, $log)
    {
        $school = config('school_name');
        $first  = trim(explode(' ', $student['first_name'])[0]);
        $time   = date('g:i A', strtotime($log['logged_at']));
        if ($event === 'arrival') return "$school: $first arrived at school at $time.";
        if ($log['action'] === 'RELEASED_OVERRIDE') {
            return "$school: $first was picked up at $time by " . $log['released_to_name'] . ', a substitute approved by '
                 . ($log['approver_name'] ?: 'the school') . '. If this is not right, call the school now.';
        }
        return "$school: $first was picked up at $time by " . $log['released_to_name'] . '.';
    }

    /** Channels in use: 'email' and/or 'sms'. */
    public static function channels()
    {
        $c = config('notifications.channels');
        return is_array($c) && $c ? array_values(array_intersect($c, ['email', 'sms'])) : ['email'];
    }

    public static function validEmail($raw)
    {
        $e = trim((string)$raw);
        return ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) ? $e : null;
    }

    /** The guardian's address for a channel, or null when there is no usable one. */
    public static function addressFor($channel, array $g)
    {
        return $channel === 'email' ? self::validEmail($g['email'] ?? '') : self::normalizePhone($g['contact_number'] ?? ($g['contact'] ?? ''));
    }

    /** True when a guardian can be reached on at least one channel in use. */
    public static function reachable(array $g)
    {
        foreach (self::channels() as $ch) if (self::addressFor($ch, $g)) return true;
        return false;
    }

    /** For screens: what is missing, e.g. "No valid email address". */
    public static function missingText()
    {
        $names = array_map(function ($c) { return $c === 'email' ? 'email address' : 'mobile number'; }, self::channels());
        return 'No valid ' . implode(' or ', $names);
    }

    /** True when at least one channel really sends (not just simulates). */
    public static function isLive()
    {
        foreach (self::channels() as $ch) {
            if ($ch === 'email' && config('notifications.email.driver') === 'smtp') return true;
            if ($ch === 'sms' && config('notifications.driver') === 'semaphore') return true;
        }
        return false;
    }

    /** Create and send the messages for one gate event. Never throws. Returns the notification ids. */
    public static function notify($studentId, $event, $logId)
    {
        if (!config('notifications.enabled')) return [];
        try {
            $student = Student::find($studentId);
            $log = Log::find($logId);
            if (!$student || !$log) return [];
            $message = self::compose($event, $student, $log);
            $ids = []; $seen = [];
            foreach (Student::notifyRecipients($studentId) as $g) {
                foreach (self::channels() as $ch) {
                    $addr = self::addressFor($ch, $g);
                    if ($addr && isset($seen[$ch . '|' . $addr])) continue;   // two guardians, one address: send once
                    if ($addr) $seen[$ch . '|' . $addr] = true;
                    $ids[] = Notification::create([
                        'student_id' => (int)$studentId, 'guardian_id' => (int)$g['id'], 'log_id' => (int)$logId, 'event' => $event,
                        'channel' => $ch, 'recipient' => $addr ?: '(none)', 'recipient_name' => $g['full_name'], 'message' => $message,
                        'status' => $addr ? 'queued' : 'failed',
                        'note' => $addr ? null : ($ch === 'email' ? 'No valid email address on file' : 'No valid mobile number on file'),
                    ]);
                }
            }
            self::dispatch($ids);
            return $ids;
        } catch (Throwable $e) {
            error_log('SGVMS notifications: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Send queued messages now.
     * The gate never waits more than notifications.timeout_seconds IN TOTAL: if the service is slow,
     * the messages that did not fit stay "queued" and can be sent later with the Try again button.
     */
    public static function dispatch(array $ids)
    {
        $deadline = microtime(true) + max(1, (int)config('notifications.timeout_seconds'));
        foreach ($ids as $id) {
            try {
                $n = Notification::find($id);
                if (!$n || $n['status'] === 'sent' || $n['status'] === 'simulated') continue;
                if ($n['recipient'] === '(none)') continue;
                $left = $deadline - microtime(true);
                if ($left <= 0.3) continue;                          // out of time: stays queued
                [$status, $note] = self::deliver($n, (int)ceil($left));
                Notification::setResult($id, $status, $note);
            } catch (Throwable $e) {
                error_log('SGVMS notifications: ' . $e->getMessage());
                try { Notification::setResult($id, 'failed', 'Could not send: ' . $e->getMessage()); } catch (Throwable $e2) {}
            }
        }
    }

    /** Returns [status, note]. */
    private static function deliver(array $n, $timeout)
    {
        if ($n['channel'] === 'email') {
            if (config('notifications.email.driver') !== 'smtp') return ['simulated', 'Simulated: nothing was really sent'];
            $subject = config('school_name') . ': ' . ($n['event'] === 'arrival' ? 'arrival notice' : 'pickup notice');
            $body = $n['message'] . "\n\nThis is an automatic message from " . config('school_name') . '. Please do not reply to this email.';
            [$ok, $note] = Mailer::send($n['recipient'], $n['recipient_name'], $subject, $body, $timeout);
            return [$ok ? 'sent' : 'failed', $note];
        }
        if (config('notifications.driver') === 'semaphore') return self::viaSemaphore($n['recipient'], $n['message'], $timeout);
        return ['simulated', 'Simulated: nothing was really sent'];
    }

    private static function viaSemaphore($number, $message, $timeout)
    {
        $c = config('notifications.semaphore');
        if (empty($c['api_key'])) return ['failed', 'No SMS API key is set in the configuration'];
        $post = ['apikey' => $c['api_key'], 'number' => $number, 'message' => $message];
        if (!empty($c['sender_name'])) $post['sendername'] = $c['sender_name'];
        $timeout = max(1, $timeout);

        if (function_exists('curl_init')) {
            $ch = curl_init($c['url']);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($post), 'timeout' => $timeout, 'ignore_errors' => true]]);
            $body = @file_get_contents($c['url'], false, $ctx);
            $err  = $body === false ? 'connection failed' : '';
            $code = 0;
            if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
        }
        if ($body === false || $err) return ['failed', 'SMS service not reachable: ' . ($err ?: 'no response')];

        $data = json_decode($body, true);
        if ($code >= 200 && $code < 300 && is_array($data) && isset($data[0]['message_id'])) {
            return ['sent', 'Accepted by the SMS service (message ' . $data[0]['message_id'] . ', ' . ($data[0]['status'] ?? 'queued') . ')'];
        }
        $text = is_array($data) ? json_encode($data) : (string)$body;
        return ['failed', 'The SMS service refused it (HTTP ' . $code . '): ' . str_replace($c['api_key'], '***', substr($text, 0, 150))];
    }
}
