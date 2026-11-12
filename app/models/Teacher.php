<?php
class Teacher extends Model
{
    public static function findByUser($userId) { return self::one('SELECT * FROM teachers WHERE user_id = ?', [(int)$userId]); }

    public static function ensure($userId, $contact, $active = true)
    {
        $t = self::findByUser($userId);
        if ($t) {
            self::run('UPDATE teachers SET contact_number = ?, status = ? WHERE teacher_id = ?', [nn($contact), $active ? 'active' : 'inactive', $t['teacher_id']]);
            return (int)$t['teacher_id'];
        }
        return self::insert('INSERT INTO teachers (user_id, contact_number, status) VALUES (?,?,?)', [(int)$userId, nn($contact), $active ? 'active' : 'inactive']);
    }

    /** Active teachers for the adviser drop-down. */
    public static function options()
    {
        return self::all("SELECT t.teacher_id AS id, CONCAT_WS(' ', u.first_name, u.last_name) AS name
                          FROM teachers t JOIN users u ON u.user_id = t.user_id
                          WHERE t.status = 'active' AND u.status = 'active' ORDER BY u.last_name, u.first_name");
    }
}
