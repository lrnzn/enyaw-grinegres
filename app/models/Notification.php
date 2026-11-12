<?php
/** notifications: the outbox of messages to parents. Rows are added, and only their send result is updated. */
class Notification extends Model
{
    public static function create($d)
    {
        return self::insert(
            'INSERT INTO notifications (student_id, guardian_id, log_id, event, channel, recipient, recipient_name, message, status, provider_note, sent_at, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$d['student_id'], $d['guardian_id'], $d['log_id'], $d['event'], $d['channel'] ?? 'sms', $d['recipient'], $d['recipient_name'],
             $d['message'], $d['status'] ?? 'queued', $d['note'] ?? null, null, date('Y-m-d H:i:s')]
        );
    }

    public static function find($id) { return self::one('SELECT *, notification_id AS id FROM notifications WHERE notification_id = ?', [(int)$id]); }

    public static function setResult($id, $status, $note)
    {
        self::run('UPDATE notifications SET status = ?, provider_note = ?, sent_at = ? WHERE notification_id = ?',
            [$status, $note !== null ? substr($note, 0, 255) : null, in_array($status, ['sent', 'simulated'], true) ? date('Y-m-d H:i:s') : null, (int)$id]);
    }

    public static function forLog($logId)
    {
        return self::all('SELECT *, notification_id AS id FROM notifications WHERE log_id = ? ORDER BY notification_id', [(int)$logId]);
    }

    public static function query($from = null, $to = null, $status = '', $limit = 300)
    {
        $sql = 'SELECT n.*, n.notification_id AS id FROM notifications n WHERE 1=1';
        $p = [];
        if ($from)   { $sql .= ' AND n.created_at >= ?'; $p[] = "$from 00:00:00"; }
        if ($to)     { $sql .= ' AND n.created_at <= ?'; $p[] = "$to 23:59:59"; }
        if ($status) { $sql .= ' AND n.status = ?';      $p[] = $status; }
        return self::all($sql . ' ORDER BY n.notification_id DESC LIMIT ' . (int)$limit, $p);
    }

    /** Failed or still-waiting messages that have a real number and could be sent now. */
    public static function retryableIds($limit = 50)
    {
        return array_column(self::all("SELECT notification_id FROM notifications WHERE status IN ('failed','queued') AND recipient <> '(none)' ORDER BY notification_id LIMIT " . (int)$limit), 'notification_id');
    }

    public static function counts($from, $to)
    {
        $rows = self::all('SELECT status, COUNT(*) AS c FROM notifications WHERE created_at BETWEEN ? AND ? GROUP BY status', ["$from 00:00:00", "$to 23:59:59"]);
        $m = ['queued' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0];
        foreach ($rows as $r) $m[$r['status']] = (int)$r['c'];
        return $m;
    }
}
