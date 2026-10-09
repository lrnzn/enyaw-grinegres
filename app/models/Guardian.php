<?php
class Guardian extends Model
{
    public static function find($id) { return self::one('SELECT * FROM guardians WHERE id = ?', [(int)$id]); }

    public static function create($d)
    {
        return self::insert(
            'INSERT INTO guardians (full_name, contact, occupation, photo, created_at) VALUES (?,?,?,?,?)',
            [$d['full_name'], nn($d['contact']), nn($d['occupation']), $d['photo'] ?? null, date('Y-m-d H:i:s')]
        );
    }

    public static function link($studentId, $guardianId, $relationship, $authorized = 1)
    {
        if (self::one('SELECT id FROM student_guardians WHERE student_id = ? AND guardian_id = ?', [(int)$studentId, (int)$guardianId])) return;
        self::run('INSERT INTO student_guardians (student_id, guardian_id, relationship, is_authorized) VALUES (?,?,?,?)',
            [(int)$studentId, (int)$guardianId, $relationship, (int)$authorized]);
    }

    public static function linkInfo($linkId)  { return self::one('SELECT * FROM student_guardians WHERE id = ?', [(int)$linkId]); }

    /** The registered, authorized link between a student and a guardian (or null). */
    public static function authorizedLink($studentId, $guardianId)
    {
        return self::one('SELECT * FROM student_guardians WHERE student_id = ? AND guardian_id = ? AND is_authorized = 1',
            [(int)$studentId, (int)$guardianId]);
    }

    public static function setAuthorized($linkId, $flag) { self::run('UPDATE student_guardians SET is_authorized = ? WHERE id = ?', [(int)$flag, (int)$linkId]); }
    public static function unlink($linkId)               { self::run('DELETE FROM student_guardians WHERE id = ?', [(int)$linkId]); }

    /** Guardians not yet linked to this student (for linking a sibling's guardian). */
    public static function notLinkedTo($studentId)
    {
        return self::all(
            'SELECT * FROM guardians WHERE id NOT IN (SELECT guardian_id FROM student_guardians WHERE student_id = ?) ORDER BY full_name',
            [(int)$studentId]
        );
    }
}
