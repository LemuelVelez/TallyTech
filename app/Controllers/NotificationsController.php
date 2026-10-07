<?php
namespace App\Controllers;
class NotificationsController extends BaseController
{
    public function index(){ $repo=$this->repository();$notifications=$repo->notifications(100);$repo->markNotificationsRead((int)session()->get('user_id'));return view('notifications/index',['title'=>'Notifications','notifications'=>$notifications]); }

    public function unreadCount()
    {
        try {
            $unread = $this->repository()->unreadNotificationCount((int) session()->get('user_id'));
        } catch (\Throwable $error) {
            log_message('error', 'Unable to load unread notification count: {message}', [
                'message' => $error->getMessage(),
            ]);
            $unread = 0;
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setJSON(['unread' => $unread]);
    }
}
