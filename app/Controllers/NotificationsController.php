<?php
namespace App\Controllers;
class NotificationsController extends BaseController
{
    public function index(){ $repo=$this->repository();$notifications=$repo->notifications(100);$repo->markNotificationsRead((int)session()->get('user_id'));return view('notifications/index',['title'=>'Notifications','notifications'=>$notifications]); }
}
