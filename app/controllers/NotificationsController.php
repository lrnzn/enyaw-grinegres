<?php
class NotificationsController extends Controller
{
    public function index()
    {
        $this->requireAdmin();
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $status = $_GET['status'] ?? '';
        if (!in_array($status, ['queued', 'sent', 'simulated', 'failed'], true)) $status = '';
        $this->view('notifications/index', [
            'title' => 'Messages to parents', 'active' => 'messages', 'date' => $date, 'status' => $status,
            'rows' => Notification::query($date, $date, $status),
            'counts' => Notification::counts($date, $date),
            'live' => Notifier::isLive(), 'channels' => Notifier::channels(), 'enabled' => (bool)config('notifications.enabled'),
            'retryable' => count(Notification::retryableIds()),
        ]);
    }

    public function retry()
    {
        $this->post();
        $this->requireAdmin();
        $ids = Notification::retryableIds();
        Notifier::dispatch($ids);
        Audit::record('MESSAGES_RETRIED', 'notification', null, count($ids) . ' unsent message(s) tried again');
        flash('success', count($ids) ? 'Tried again for ' . count($ids) . ' unsent message(s). See the new status below.' : 'There are no unsent messages to try again.');
        $this->redirect(url('notifications'));
    }
}
