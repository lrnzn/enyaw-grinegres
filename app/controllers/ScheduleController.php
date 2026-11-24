<?php
/** Admin: the bell schedule (periods), the subjects, and who teaches which section when. Nothing is fixed; the school edits it all. */
class ScheduleController extends Controller
{
    private const SAMPLE_PERIODS = [
        ['Period 1', '07:30', '08:20'], ['Period 2', '08:20', '09:10'], ['Period 3', '09:30', '10:20'], ['Period 4', '10:20', '11:10'],
        ['Period 5', '13:00', '13:50'], ['Period 6', '13:50', '14:40'], ['Period 7', '14:50', '15:40'],
    ];
    private const SAMPLE_SUBJECTS = ['Bible', 'English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'MAPEH', 'Computer', 'Values Education'];

    public function index()
    {
        $this->requireAdmin();
        $section = (int)($_GET['section'] ?? 0);
        $sections = Section::options();
        if (!$section && $sections) $section = (int)$sections[0]['id'];
        $this->view('schedule/index', [
            'title' => 'Schedules', 'active' => 'schedule',
            'periods' => Period::listAll(), 'subjects' => Subject::listAll(), 'sections' => $sections, 'section' => $section,
            'classes' => $section ? Schedule::forSection($section) : [], 'teachers' => Teacher::options(),
        ]);
    }

    public function addPeriod()
    {
        $this->post(); $this->requireAdmin();
        [$name, $start, $end] = $this->periodInput();
        if ($name && !$this->tryRun(function () use ($name, $start, $end) { Period::create($name, $start, $end); })) flash('error', 'A period with that name already exists.');
        elseif ($name) flash('success', "$name added.");
        $this->back();
    }

    public function updatePeriod($id = 0)
    {
        $this->post(); $this->requireAdmin();
        Period::find($id) ?: $this->back();
        [$name, $start, $end] = $this->periodInput();
        if ($name && !$this->tryRun(function () use ($id, $name, $start, $end) { Period::update($id, $name, $start, $end); })) flash('error', 'A period with that name already exists.');
        elseif ($name) flash('success', 'Period saved.');
        $this->back();
    }

    public function deletePeriod($id = 0)
    {
        $this->post(); $this->requireAdmin();
        $p = Period::find($id) ?: $this->back();
        if (!Period::inUse($id) && $this->tryRun(function () use ($id) { Period::delete($id); })) { Audit::record('PERIOD_DELETED', 'period', (int)$id, $p['name']); flash('success', 'Period removed.'); }
        else flash('error', 'That period is used by class schedules. Remove those classes first.');
        $this->back();
    }

    public function addSubject()
    {
        $this->post(); $this->requireAdmin();
        $name = substr($this->input('name'), 0, 80);
        if ($name === '') flash('error', 'Type the subject name.');
        elseif (!$this->tryRun(function () use ($name) { Subject::create($name); })) flash('error', 'That subject already exists.');
        else flash('success', "$name added.");
        $this->back();
    }

    public function deleteSubject($id = 0)
    {
        $this->post(); $this->requireAdmin();
        if (!Subject::inUse($id) && $this->tryRun(function () use ($id) { Subject::delete($id); })) flash('success', 'Subject removed.');
        else flash('error', 'That subject is used by class schedules. Remove those classes first.');
        $this->back();
    }

    /** One click to fill an empty list with a starting point the school can edit. */
    public function samples()
    {
        $this->post(); $this->requireAdmin();
        if ($this->input('what') === 'periods' && Period::count() === 0) {
            foreach (self::SAMPLE_PERIODS as [$n, $s, $e]) Period::create($n, $s, $e);
            flash('success', 'Sample bell schedule added. Change the names and times to match the school.');
        } elseif ($this->input('what') === 'subjects' && Subject::count() === 0) {
            foreach (self::SAMPLE_SUBJECTS as $n) Subject::create($n);
            flash('success', 'Sample subjects added. Add or remove subjects as needed.');
        }
        $this->back();
    }

    public function addClass()
    {
        $this->post(); $this->requireAdmin();
        $sec = (int)$this->input('section'); $sub = (int)$this->input('subject'); $tch = (int)$this->input('teacher');
        $per = (int)$this->input('period'); $day = (int)$this->input('day');
        if (!Section::find($sec) || !$sub || !$tch || !$per || !isset(Schedule::DAYS[$day])) { flash('error', 'Choose the day, period, subject and teacher.'); $this->back($sec); }
        if ($msg = Schedule::conflict($sec, $tch, $day, $per)) { flash('error', $msg); $this->back($sec); }
        if (!$this->tryRun(function () use ($sec, $sub, $tch, $per, $day) { Schedule::create($sec, $sub, $tch, $per, $day); })) flash('error', 'Could not add the class. Check the subject, teacher and period.');
        else flash('success', 'Class added.');
        $this->back($sec);
    }

    public function deleteClass($id = 0)
    {
        $this->post(); $this->requireAdmin();
        $c = Schedule::find($id) ?: $this->back();
        if (Schedule::hasAttendance($id)) flash('error', 'This class already has attendance records, so it is kept. Records are never deleted.');
        else { Schedule::delete($id); Audit::record('CLASS_REMOVED', 'schedule', (int)$id, Schedule::label($c)); flash('success', 'Class removed.'); }
        $this->back((int)$c['section_id']);
    }

    private function periodInput()
    {
        $name = substr($this->input('name'), 0, 40);
        $start = $this->input('start_time'); $end = $this->input('end_time');
        if ($name === '' || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) { flash('error', 'Give the period a name, a start time and an end time.'); return [null, null, null]; }
        if ($end <= $start) { flash('error', 'The end time must be later than the start time.'); return [null, null, null]; }
        return [$name, $start, $end];
    }

    /** True when the action worked; false when the database refused it (duplicate or still in use). */
    private function tryRun(callable $fn)
    {
        try { $fn(); return true; } catch (PDOException $e) { return false; }
    }

    private function back($section = 0)
    {
        $section = $section ?: (int)($_POST['section'] ?? 0);
        $this->redirect(url('schedule') . ($section ? '&section=' . $section : ''));
    }
}
