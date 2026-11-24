<?php
class ReportsController extends Controller
{
    /** Printable attendance sheet for a section (whole day) or for one subject of a section (class periods). */
    public function sheet()
    {
        $this->requireRole(['teacher']);
        $teacherId = is_teacher() ? (int)(Teacher::findByUser(current_user()['id'])['teacher_id'] ?? 0) : null;
        [$from, $to, $range] = Report::range($_GET, is_teacher());
        if ($range === 'today') [$from, $to, $range] = Report::range(['range' => 'week', 'date' => $from], is_teacher());

        $canDaily = Section::hasDaily();
        $type = ($_GET['type'] ?? ($canDaily ? 'daily' : 'period')) === 'period' || !$canDaily ? 'period' : 'daily';
        $sectionId = (int)($_GET['section'] ?? 0);
        $subjectId = (int)($_GET['subject'] ?? 0);
        $sections = $type === 'daily' ? Section::dailyOptions() : Section::options();
        if (!in_array($sectionId, array_map('intval', array_column($sections, 'id')), true)) $sectionId = 0;
        if (!$sectionId && $sections) $sectionId = (int)$sections[0]['id'];

        $section = $sectionId ? Report::sectionInfo($sectionId) : null;
        $subjects = $section ? Report::subjectsOf($sectionId, $teacherId) : [];
        if ($type === 'period' && !$subjectId && $subjects) $subjectId = (int)$subjects[0]['id'];

        $sheet = null;
        if ($section) {
            $sheet = $type === 'period'
                ? ($subjectId ? Report::periodSheet($sectionId, $subjectId, $from, $to, $teacherId) : null)
                : Report::dailySheet($sectionId, $from, $to);
        }
        $subject = null;
        foreach ($subjects as $s) if ((int)$s['id'] === $subjectId) $subject = $s;

        $active = 'attendance'; $title = 'Attendance sheet';
        $this->view('reports/sheet', compact('active', 'title', 'canDaily', 'type', 'from', 'to', 'range', 'sections', 'section', 'sectionId', 'subjects', 'subjectId', 'subject', 'sheet'));
    }
}
