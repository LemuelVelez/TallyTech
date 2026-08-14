<?php
namespace App\Controllers;
class NotificationsController extends BaseController
{ public function index(){return view('notifications/index',['title'=>'Notifications','notifications'=>$this->repository()->notifications(100)]);} }
