<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $data=$this->scoringService()->dashboard((string)session()->get('role'));
        $data['title']='Dashboard';
        return view('dashboard/index',$data);
    }
    public function ranking()
    {
        $event=$this->repository()->activeEvent();
        return view('dashboard/ranking',['title'=>'Team Ranking','activeEvent'=>$event,'ranking'=>$this->repository()->ranking((int)($event['id']??0))]);
    }
}
