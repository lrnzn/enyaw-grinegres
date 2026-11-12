<?php
/** guardian_confirmations: letter + phone call + approving admin for a substitute pickup. */
class Confirmation extends Model
{
    public static function create($d)
    {
        return self::insert(
            'INSERT INTO guardian_confirmations (student_id, guardian_id, collector_id, approved_by, relationship, letter_path, phone_call_status, confirmed_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [(int)$d['student_id'], nn($d['guardian_id']), (int)$d['collector_id'], (int)$d['approved_by'],
             nn($d['relationship']), $d['letter_path'] ?? null, $d['phone_call_status'], date('Y-m-d H:i:s')]
        );
    }

    /** File names of letters for a student (so they can be deleted with the student). */
    public static function lettersFor($studentId)
    {
        return array_column(self::all('SELECT letter_path FROM guardian_confirmations WHERE student_id = ? AND letter_path IS NOT NULL', [(int)$studentId]), 'letter_path');
    }
}
