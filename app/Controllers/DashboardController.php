<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $data=$this->scoringService()->dashboard((string)session()->get('role'),(int)session()->get('user_id'));
        $data['title']='Dashboard';
        // Render first because the shared layout fetches notifications using session identity.
        $html=view('dashboard/index',$data);
        session()->close();
        return $html;
    }
    public function ranking()
    {
        $event=$this->repository()->activeEvent();
        return view('dashboard/ranking',['title'=>'Team Ranking','activeEvent'=>$event,'ranking'=>$this->repository()->ranking((int)($event['id']??0),false)]);
    }
}
