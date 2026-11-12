<?php
/** What administrators changed. Rows are only ever added. */
class Audit extends Model
{
    public static function record($action, $entityType, $entityId = null, $details = null)
    {
        self::run('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, created_at) VALUES (?,?,?,?,?,?)',
            [current_user()['id'] ?? null, $action, $entityType, $entityId, $details !== null ? substr($details, 0, 255) : null, date('Y-m-d H:i:s')]);
    }

    public static function query($from = null, $to = null, $limit = 300)
    {
        $sql = "SELECT a.*, CONCAT_WS(' ', u.first_name, u.last_name) AS user_name FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id WHERE 1=1";
        $p = [];
        if ($from) { $sql .= ' AND a.created_at >= ?'; $p[] = "$from 00:00:00"; }
        if ($to)   { $sql .= ' AND a.created_at <= ?'; $p[] = "$to 23:59:59"; }
        return self::all($sql . ' ORDER BY a.audit_id DESC LIMIT ' . (int)$limit, $p);
    }
}
