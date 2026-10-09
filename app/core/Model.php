<?php
/** Tiny base class: thin helpers around PDO prepared statements. */
abstract class Model
{
    protected static function run($sql, $params = [])
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }
    protected static function all($sql, $params = []) { return self::run($sql, $params)->fetchAll(); }
    protected static function one($sql, $params = []) { $r = self::run($sql, $params)->fetch(); return $r ?: null; }
    protected static function insert($sql, $params = []) { self::run($sql, $params); return (int)Database::pdo()->lastInsertId(); }
}
