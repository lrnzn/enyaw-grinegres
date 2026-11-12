<?php
class SectionsController extends Controller
{
    private const GRADES = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];

    private static function currentYear()
    {
        $y = (int)date('Y');
        return date('n') >= 6 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    public function index()
    {
        $this->requireAdmin();
        $this->view('sections/index', ['title' => 'Sections', 'active' => 'sections',
            'sections' => Section::listAll(), 'teachers' => Teacher::options(), 'grades' => self::GRADES, 'year' => self::currentYear()]);
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
