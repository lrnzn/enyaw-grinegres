<?php
/** The bell schedule: named time slots of the school day. */
class Period extends Model
{
    public static function listAll()
    {
        return self::all("SELECT period_id AS id, name, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time,
                                 (SELECT COUNT(*) FROM class_schedules cs WHERE cs.period_id = periods.period_id) AS used
                          FROM periods ORDER BY start_time, name");
    }
    public static function find($id) { return self::one('SELECT period_id AS id, name FROM periods WHERE period_id = ?', [(int)$id]); }
    public static function create($name, $start, $end) { return self::insert('INSERT INTO periods (name, start_time, end_time) VALUES (?,?,?)', [$name, $start, $end]); }
    public static function update($id, $name, $start, $end) { self::run('UPDATE periods SET name = ?, start_time = ?, end_time = ? WHERE period_id = ?', [$name, $start, $end, (int)$id]); }
    public static function inUse($id) { return (bool)self::one('SELECT 1 AS x FROM class_schedules WHERE period_id = ? LIMIT 1', [(int)$id]); }
    public static function delete($id) { self::run('DELETE FROM periods WHERE period_id = ?', [(int)$id]); }
    public static function count() { return (int)(self::one('SELECT COUNT(*) AS n FROM periods')['n'] ?? 0); }
}
