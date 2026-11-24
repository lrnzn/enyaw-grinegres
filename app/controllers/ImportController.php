<?php
/** Admin: register many students (and their guardians) at once from a CSV file. Upload, check the preview, then confirm. */
class ImportController extends Controller
{
    private const MAX_ROWS = 1000;
    private const COLUMNS = ['first_name', 'middle_name', 'last_name', 'suffix', 'lrn', 'grade', 'section', 'school_year', 'gender', 'birthdate', 'address',
        'g1_first_name', 'g1_last_name', 'g1_relationship', 'g1_email', 'g1_phone',
        'g2_first_name', 'g2_last_name', 'g2_relationship', 'g2_email', 'g2_phone'];

    public function index()
    {
        $this->requireAdmin();
        $this->view('import/index', ['title' => 'Import students', 'active' => 'students', 'preview' => null, 'year' => Section::currentYear()]);
    }

    /** A sample file the school can fill in. */
    public function template()
    {
        $this->requireAdmin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sgvms-student-import-template.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // so Excel opens it as UTF-8
        fputcsv($out, self::COLUMNS);
        fputcsv($out, ['Juan', 'Santos', 'Dela Cruz', '', '', 'Grade 6', 'Sampaguita', '', 'male', '2014-05-20', 'Sample address',
            'Maria', 'Dela Cruz', 'Mother', 'maria@example.com', '0917 123 4567', 'Pedro', 'Dela Cruz', 'Father', '', '']);
        fputcsv($out, ['Ana', '', 'Reyes', '', '', 'Grade 7', 'Rizal', '', 'female', '2013-11-02', '',
            'Carla', 'Reyes', 'Aunt', 'carla@example.com', '', '', '', '', '', '']);
        fclose($out);
    }

    /** Step 1: read the file and show what would happen. Nothing is saved yet. */
    public function preview()
    {
        $this->post();
        $this->requireAdmin();
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('error', 'Choose a CSV file to upload.');
            $this->redirect(url('import'));
        }
        if ($f['size'] > 2 * 1024 * 1024) { flash('error', 'The file must be 2 MB or smaller.'); $this->redirect(url('import')); }
        $raw = (string)file_get_contents($f['tmp_name']);
        [$rows, $fileError] = $this->parse($raw);
        if ($fileError) { flash('error', $fileError); $this->redirect(url('import')); }

        $year = Section::currentYear();
        $checked = $this->check($rows, $year);
        $_SESSION['import'] = ['rows' => array_values(array_filter($checked, function ($r) { return $r['state'] === 'ready'; })), 'year' => $year];
        $count = ['ready' => 0, 'skip' => 0, 'error' => 0];
        foreach ($checked as $r) $count[$r['state']]++;
        $this->view('import/index', ['title' => 'Import students', 'active' => 'students', 'preview' => $checked, 'count' => $count, 'year' => $year]);
    }

    /** Step 2: save the rows that were ready in the preview. */
    public function confirm()
    {
        $this->post();
        $this->requireAdmin();
        $data = $_SESSION['import'] ?? null;
        unset($_SESSION['import']);
        if (!$data || !$data['rows']) { flash('error', 'Nothing to import. Upload the file again.'); $this->redirect(url('import')); }

        $stats = ['students' => 0, 'guardians' => 0, 'sections' => 0];
        Database::transaction(function () use ($data, &$stats) {
            $sections = [];
            foreach ($data['rows'] as $r) {
                $d = $r['d'];
                $key = $d['grade'] . '|' . $d['section'] . '|' . $d['year'];
                if (!isset($sections[$key])) {
                    $sec = Section::findByKey($d['grade'], $d['section'], $d['year']);
                    if ($sec) { $sections[$key] = (int)$sec['id']; }
                    else {
                        $sections[$key] = Section::create(['adviser_id' => null, 'section_name' => $d['section'], 'grade_level' => $d['grade'], 'school_year' => $d['year']]);
                        $stats['sections']++;
                    }
                }
                $sid = Student::create([
                    'section_id' => $sections[$key], 'lrn' => $d['lrn'], 'first_name' => $d['first_name'], 'middle_name' => $d['middle_name'],
                    'last_name' => $d['last_name'], 'name_suffix' => $d['suffix'], 'gender' => $d['gender'], 'date_of_birth' => $d['birthdate'],
                    'address' => $d['address'], 'photo' => null, 'status' => 'enrolled',
                ]);
                $stats['students']++;
                foreach ($d['guardians'] as $g) {
                    $gid = Guardian::findMatch($g['first_name'], $g['last_name'], $g['email'], $g['phone']);
                    if (!$gid) {
                        $gid = Guardian::create(['first_name' => $g['first_name'], 'middle_name' => '', 'last_name' => $g['last_name'], 'name_suffix' => '',
                            'contact' => $g['phone'], 'email' => $g['email'], 'occupation' => '', 'photo' => null]);
                        $stats['guardians']++;
                    }
                    Guardian::link($sid, $gid, $g['relationship'], 1);
                }
            }
        });
        Audit::record('STUDENTS_IMPORTED', 'student', null, "{$stats['students']} students, {$stats['guardians']} new guardians, {$stats['sections']} new sections");
        flash('success', "Imported {$stats['students']} students ({$stats['guardians']} new guardians, {$stats['sections']} new sections). Add each guardian's photo on the student's page.");
        $this->redirect(url('students'));
    }

    /* ---------- reading and checking ---------- */

    /** Returns [rows (each: line number + values by column name), error message or null]. */
    private function parse($raw)
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        $first = strtok($raw, "\r\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) if (substr_count($first, $d) > substr_count($first, $delim)) $delim = $d;

        $h = fopen('php://memory', 'w+'); fwrite($h, $raw); rewind($h);
        $header = fgetcsv($h, 0, $delim);
        if (!$header) return [[], 'The file is empty.'];
        $cols = array_map([$this, 'columnName'], $header);
        foreach (['first_name', 'last_name', 'grade', 'section'] as $need) {
            if (!in_array($need, $cols, true)) return [[], "The file has no \"$need\" column. Download the template to see the expected columns."];
        }
        $rows = []; $line = 1;
        while (($cells = fgetcsv($h, 0, $delim)) !== false) {
            $line++;
            if (count($cells) === 1 && trim((string)$cells[0]) === '') continue;   // blank line
            if (count($rows) >= self::MAX_ROWS) return [[], 'The file has more than ' . self::MAX_ROWS . ' students. Split it into smaller files.'];
            $v = [];
            foreach ($cols as $i => $name) if ($name !== '' && !isset($v[$name])) $v[$name] = trim((string)($cells[$i] ?? ''));
            $rows[] = ['line' => $line, 'v' => $v];
        }
        fclose($h);
        if (!$rows) return [[], 'The file has no student rows.'];
        return [$rows, null];
    }

    private function columnName($h)
    {
        $n = trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($h))), '_');
        $n = preg_replace('/^guardian_?([12])_/', 'g$1_', $n);
        $n = preg_replace('/^guardian_/', 'g1_', $n);
        $alias = ['name_suffix' => 'suffix', 'date_of_birth' => 'birthdate', 'birthday' => 'birthdate', 'dob' => 'birthdate', 'sex' => 'gender',
                  'grade_level' => 'grade', 'section_name' => 'section', 'firstname' => 'first_name', 'lastname' => 'last_name', 'middlename' => 'middle_name'];
        $n = $alias[$n] ?? $n;
        $n = preg_replace('/^(g[12])_(contact|contact_number|mobile|phone_number)$/', '$1_phone', $n);
        $n = preg_replace('/^(g[12])_(first|firstname)$/', '$1_first_name', $n);
        $n = preg_replace('/^(g[12])_(last|lastname)$/', '$1_last_name', $n);
        $n = preg_replace('/^(g[12])_relation$/', '$1_relationship', $n);
        return in_array($n, self::COLUMNS, true) ? $n : '';
    }

    private function grade($raw)
    {
        $t = strtolower(trim($raw));
        if (in_array($t, ['k', 'kinder', 'kindergarten'], true)) return 'Kinder';
        if (preg_match('/^(?:grade|gr\.?|g)?\s*(\d{1,2})$/', $t, $m)) {
            $g = 'Grade ' . (int)$m[1];
            return in_array($g, Section::GRADES, true) ? $g : null;
        }
        return null;
    }

    /** Check every row. state: ready (will be imported), skip (already registered), error (fix the file). */
    private function check(array $rows, $defaultYear)
    {
        $seenLrn = [];
        $out = [];
        foreach ($rows as $r) {
            $v = $r['v'] + array_fill_keys(self::COLUMNS, '');
            $errors = []; $notes = []; $skip = null;

            if ($v['first_name'] === '' || $v['last_name'] === '') $errors[] = 'First and last name are required';
            $grade = $this->grade($v['grade']);
            if (!$grade) $errors[] = 'Grade "' . $v['grade'] . '" is not valid (use Kinder or Grade 1 to Grade 10)';
            if ($v['section'] === '') $errors[] = 'Section is required';
            if (strlen($v['section']) > 60) $errors[] = 'Section name is too long';
            $year = $v['school_year'] !== '' ? $v['school_year'] : $defaultYear;
            if (!preg_match('/^\d{4}-\d{4}$/', $year)) $errors[] = 'School year should look like 2026-2027';

            $g = strtolower($v['gender']);
            $gender = in_array($g, ['female', 'f'], true) ? 'female' : (in_array($g, ['male', 'm'], true) ? 'male' : '');
            if ($g !== '' && $gender === '') $errors[] = 'Gender should be male or female';

            $birth = '';
            if ($v['birthdate'] !== '') {
                $ts = strtotime($v['birthdate']);
                if ($ts) $birth = date('Y-m-d', $ts); else $errors[] = 'Birthdate is not a valid date (use 2014-05-20)';
            }
            $lrn = $v['lrn'];
            if ($lrn !== '' && !preg_match('/^\d{6,20}$/', $lrn)) $errors[] = 'LRN should be digits only';

            $guardians = [];
            foreach (['g1', 'g2'] as $p) {
                $gf = $v["{$p}_first_name"]; $gl = $v["{$p}_last_name"]; $em = strtolower($v["{$p}_email"]); $ph = $v["{$p}_phone"];
                if ($gf === '' && $gl === '' && $em === '' && $ph === '') continue;
                if ($gf === '' || $gl === '') { $errors[] = "Guardian " . substr($p, 1) . " needs a first and last name"; continue; }
                if ($em !== '' && !Notifier::validEmail($em)) { $errors[] = "Guardian " . substr($p, 1) . " email is not valid"; continue; }
                if ($em === '') $notes[] = "$gf $gl has no email, so gets no messages";
                $guardians[] = ['first_name' => $gf, 'last_name' => $gl, 'relationship' => $v["{$p}_relationship"] ?: 'Guardian', 'email' => $em, 'phone' => $ph];
            }
            if (!$guardians && !$errors) $notes[] = 'No guardian listed';

            if (!$errors) {
                if ($lrn !== '' && isset($seenLrn[$lrn])) $errors[] = 'LRN repeats line ' . $seenLrn[$lrn];
                elseif ($lrn !== '' && Database::pdo()->query("SELECT 1 FROM students WHERE lrn = " . Database::pdo()->quote($lrn) . ' LIMIT 1')->fetchColumn()) $skip = 'A student with this LRN is already registered';
                elseif ($lrn === '' && $this->nameExists($v['first_name'], $v['last_name'], $birth)) $skip = 'A student with this name' . ($birth ? ' and birthdate' : '') . ' is already registered';
                if ($lrn !== '') $seenLrn[$lrn] = $r['line'];
                if (!$skip && !Section::findByKey($grade, $v['section'], $year)) $notes[] = "New section \"$grade, {$v['section']}\" will be created";
            }

            $out[] = [
                'line' => $r['line'], 'state' => $errors ? 'error' : ($skip ? 'skip' : 'ready'),
                'name' => trim($v['first_name'] . ' ' . $v['last_name']), 'class' => $grade ? "$grade, {$v['section']}" : trim($v['grade'] . ' ' . $v['section']),
                'guardians' => count($guardians), 'messages' => array_merge($errors, $skip ? [$skip] : [], $notes),
                'd' => ['first_name' => $v['first_name'], 'middle_name' => $v['middle_name'], 'last_name' => $v['last_name'], 'suffix' => $v['suffix'],
                        'lrn' => $lrn, 'grade' => $grade, 'section' => $v['section'], 'year' => $year, 'gender' => $gender, 'birthdate' => $birth,
                        'address' => $v['address'], 'guardians' => $guardians],
            ];
        }
        return $out;
    }

    private function nameExists($first, $last, $birth)
    {
        $sql = 'SELECT 1 FROM students WHERE first_name = ? AND last_name = ?' . ($birth ? ' AND date_of_birth = ?' : '') . ' LIMIT 1';
        $st = Database::pdo()->prepare($sql);
        $st->execute($birth ? [$first, $last, $birth] : [$first, $last]);
        return (bool)$st->fetchColumn();
    }
}
