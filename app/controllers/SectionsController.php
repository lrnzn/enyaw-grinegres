<?php
class SectionsController extends Controller
{
    private const GRADES = Section::GRADES;

    private static function currentYear()
    {
        $y = (int)date('Y');
        return date('n') >= 6 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    public function index()
    {
        $this->requireAdmin();
        $all = Section::listAll();
        $f = [
            'grade'   => in_array($_GET['grade'] ?? '', self::GRADES, true) ? $_GET['grade'] : '',
            'year'    => preg_match('/^\d{4}-\d{4}$/', $_GET['year'] ?? '') ? $_GET['year'] : '',
            'adviser' => (string)($_GET['adviser'] ?? ''),          // '' = any, 'none' = no adviser, or a teacher id
            'q'       => trim((string)($_GET['q'] ?? '')),
            'empty'   => !empty($_GET['empty']),                    // only sections with no students
        ];
        $years = array_values(array_unique(array_column($all, 'school_year')));
        rsort($years);
        $shown = array_values(array_filter($all, function ($s) use ($f) {
            if ($f['grade'] && $s['grade_level'] !== $f['grade']) return false;
            if ($f['year'] && $s['school_year'] !== $f['year']) return false;
            if ($f['adviser'] === 'none' && $s['adviser_id']) return false;
            if ($f['adviser'] !== '' && $f['adviser'] !== 'none' && (int)$s['adviser_id'] !== (int)$f['adviser']) return false;
            if ($f['q'] !== '' && stripos($s['section_name'], $f['q']) === false) return false;
            if ($f['empty'] && (int)$s['student_count'] > 0) return false;
            return true;
        }));
        $this->view('sections/index', ['title' => 'Sections', 'active' => 'sections', 'sections' => $shown, 'total' => count($all), 'f' => $f, 'years' => $years,
            'teachers' => Teacher::options(), 'grades' => self::GRADES, 'year' => self::currentYear()]);
    }

    public function store()
    {
        $this->post();
        $this->requireAdmin();
        [$d, $err] = $this->validated();
        if ($err) { flash('error', $err); $this->redirect(url('sections')); }
        try {
            $id = Section::create($d);
            Audit::record('SECTION_CREATED', 'section', $id, $d['grade_level'] . ', ' . $d['section_name'] . ' ' . $d['school_year']);
            flash('success', 'Section added.');
        } catch (PDOException $e) {
            flash('error', 'That section already exists for this school year.');
        }
        $this->redirect(url('sections'));
    }

    public function edit($id = 0)
    {
        $this->requireAdmin();
        $sec = Section::find($id) ?: $this->redirect(url('sections'));
        $this->view('sections/form', ['title' => 'Edit section', 'active' => 'sections',
            'sec' => $sec, 'teachers' => Teacher::options(), 'grades' => self::GRADES]);
    }

    public function update($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        Section::find($id) ?: $this->redirect(url('sections'));
        [$d, $err] = $this->validated();
        if ($err) { flash('error', $err); $this->redirect(url('sections/edit/' . $id)); }
        try {
            Section::update($id, $d);
            Audit::record('SECTION_UPDATED', 'section', (int)$id, $d['grade_level'] . ', ' . $d['section_name'] . ' ' . $d['school_year']);
            flash('success', 'Section updated.');
        } catch (PDOException $e) {
            flash('error', 'That section already exists for this school year.');
            $this->redirect(url('sections/edit/' . $id));
        }
        $this->redirect(url('sections'));
    }

    public function delete($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $sec = Section::find($id) ?: $this->redirect(url('sections'));
        if (Schedule::sectionHasAttendance($id)) {
            flash('error', 'This section has period attendance records, so it is kept. Records are never deleted.');
            $this->redirect(url('sections'));
        }
        Section::delete($id);
        Audit::record('SECTION_DELETED', 'section', (int)$id, $sec['grade_level'] . ', ' . $sec['section_name']);
        flash('success', 'Section removed. Its students are kept and now have no section.');
        $this->redirect(url('sections'));
    }

    private function validated()
    {
        $d = ['section_name' => $this->input('section_name'), 'grade_level' => $this->input('grade_level'),
              'school_year' => $this->input('school_year'), 'adviser_id' => $this->input('adviser_id')];
        if ($d['section_name'] === '') return [$d, 'Section name is required.'];
        if (!in_array($d['grade_level'], self::GRADES, true)) return [$d, 'Choose a grade level.'];
        if (!preg_match('/^\d{4}-\d{4}$/', $d['school_year'])) return [$d, 'School year should look like 2026-2027.'];
        return [$d, null];
    }
}
