<?php
class Guardian extends Model
{
    public static function find($id)
    {
        $r = self::one('SELECT *, guardian_id AS id, contact_number AS contact, photo_path AS photo FROM guardians WHERE guardian_id = ?', [(int)$id]);
        if ($r) $r['full_name'] = compose_name($r);
        return $r;
    }

    public static function create($d)
    {
        return self::insert(
            'INSERT INTO guardians (first_name, middle_name, last_name, name_suffix, contact_number, occupation, photo_path) VALUES (?,?,?,?,?,?,?)',
            [$d['first_name'], nn($d['middle_name']), $d['last_name'], nn($d['name_suffix']), nn($d['contact']), nn($d['occupation']), $d['photo'] ?? null]
        );
    }

    public static function link($studentId, $guardianId, $relationship, $authorized = 1)
    {
        if (self::one('SELECT student_guardian_id FROM student_guardians WHERE student_id = ? AND guardian_id = ?', [(int)$studentId, (int)$guardianId])) return;
        self::run('INSERT INTO student_guardians (student_id, guardian_id, relationship, is_authorized) VALUES (?,?,?,?)',
            [(int)$studentId, (int)$guardianId, $relationship, (int)$authorized]);
    }

    public static function linkInfo($linkId)
    {
        return self::one('SELECT *, student_guardian_id AS id FROM student_guardians WHERE student_guardian_id = ?', [(int)$linkId]);
    }

    /** The registered, authorized link between a student and a guardian (or null). */
    public static function authorizedLink($studentId, $guardianId)
    {
        return self::one('SELECT * FROM student_guardians WHERE student_id = ? AND guardian_id = ? AND is_authorized = 1',
            [(int)$studentId, (int)$guardianId]);
    }

    public static function setAuthorized($linkId, $flag) { self::run('UPDATE student_guardians SET is_authorized = ? WHERE student_guardian_id = ?', [(int)$flag, (int)$linkId]); }
    public static function setNotify($linkId, $flag)    { self::run('UPDATE student_guardians SET receive_notifications = ? WHERE student_guardian_id = ?', [(int)$flag, (int)$linkId]); }
    public static function unlink($linkId)               { self::run('DELETE FROM student_guardians WHERE student_guardian_id = ?', [(int)$linkId]); }

    /** Remove a guardian who is no longer linked to any student (and return the photo file name to delete). */
    public static function deleteIfOrphan($guardianId)
    {
        $g = self::find($guardianId);
        if (!$g) return null;
        if (self::one('SELECT student_guardian_id FROM student_guardians WHERE guardian_id = ? LIMIT 1', [(int)$guardianId])) return null;
        self::run('DELETE FROM guardians WHERE guardian_id = ?', [(int)$guardianId]);
        return $g['photo'];
    }

    /** Guardians not yet linked to this student (for linking a sibling's guardian). */
    public static function notLinkedTo($studentId)
    {
        $rows = self::all(
            'SELECT *, guardian_id AS id FROM guardians
             WHERE guardian_id NOT IN (SELECT guardian_id FROM student_guardians WHERE student_id = ?) ORDER BY last_name, first_name',
            [(int)$studentId]);
        foreach ($rows as &$r) $r['full_name'] = compose_name($r);
        return $rows;
    }
}
