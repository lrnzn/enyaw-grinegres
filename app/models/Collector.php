<?php
/** A person collecting a child who is not a registered guardian. */
class Collector extends Model
{
    public static function create($d)
    {
        return self::insert('INSERT INTO collectors (first_name, middle_name, last_name, contact_number, photo_path) VALUES (?,?,?,?,?)',
            [$d['first_name'], nn($d['middle_name'] ?? null), $d['last_name'], nn($d['contact']), $d['photo'] ?? null]);
    }
}
