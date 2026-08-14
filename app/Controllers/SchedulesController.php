<?php
namespace App\Controllers;
class SchedulesController extends BaseController
{
    public function index(){ $d=$this->scoringService()->commonData(); $d['title']='Team Schedules'; $d['schedules']=$this->repository()->schedules((int)($d['activeEvent']['id']??0)); return view('schedules/index',$d); }
    public function store(){
        $event=$this->repository()->activeEvent(); if(!$event) return redirect()->back()->with('error','Add and activate an event first.');
        $sportId=(int)$this->request->getPost('sport_id'); $loc=(int)$this->request->getPost('location_id'); $date=(string)$this->request->getPost('match_date');
        if(!$sportId||!$loc||!$date) return redirect()->back()->with('error','Sport, location, and match date are required.');
        $this->repository()->createSchedule(['event_id'=>$event['id'],'sport_id'=>$sportId,'location_id'=>$loc,'round'=>trim((string)$this->request->getPost('round'))?:'Elimination','match_date'=>str_replace('T',' ',$date).(strlen($date)===16?':00':''),'team_a_id'=>$this->request->getPost('team_a_id')?:null,'team_b_id'=>$this->request->getPost('team_b_id')?:null,'status'=>'scheduled','created_at'=>date('Y-m-d H:i:s')],(int)session()->get('user_id'));
        return redirect()->back()->with('success','Schedule added.');
    }
}
