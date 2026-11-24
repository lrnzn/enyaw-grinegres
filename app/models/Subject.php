<?php
class Subject extends Model
{
    public static function listAll()
    {
        return self::all('SELECT subject_id AS id, name, (SELECT COUNT(*) FROM class_schedules cs WHERE cs.subject_id = subjects.subject_id) AS used FROM subjects ORDER BY name');
    }
    public static function create($name) { return self::insert('INSERT INTO subjects (name) VALUES (?)', [$name]); }
    public static function inUse($id) { return (bool)self::one('SELECT 1 AS x FROM class_schedules WHERE subject_id = ? LIMIT 1', [(int)$id]); }
    public static function delete($id) { self::run('DELETE FROM subjects WHERE subject_id = ?', [(int)$id]); }
    public static function count() { return (int)(self::one('SELECT COUNT(*) AS n FROM subjects')['n'] ?? 0); }
}
