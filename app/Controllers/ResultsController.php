<?php
namespace App\Controllers;
class ResultsController extends BaseController
{
    public function matches(){return $this->page('match','Match Results');}
    public function judged(){return $this->page('judged','Judged Results');}
    private function page(string $type,string $title){
        $repo=$this->repository(); $e=$repo->activeEvent(); $eid=(int)($e['id']??0);
        $schedules=$repo->schedules($eid,$type); $results=$repo->results($eid,$type);
        if(session()->get('role')==='facilitator'){
            $allowed=$repo->assignedSportIds((int)session()->get('user_id'));
            $schedules=array_values(array_filter($schedules,fn($s)=>in_array((int)$s['sport_id'],$allowed,true)));
            $scheduleIds=array_map('intval',array_column($schedules,'id'));
            $results=array_values(array_filter($results,fn($r)=>in_array((int)$r['schedule_id'],$scheduleIds,true)));
        }
        return view('results/index',['title'=>$title,'resultType'=>$type,'activeEvent'=>$e,'schedules'=>$schedules,'results'=>$results,'teams'=>$repo->teams()]);
    }
    public function store(){
        try{$this->scoringService()->saveResult(['schedule_id'=>(int)$this->request->getPost('schedule_id'),'team_a_score'=>$this->request->getPost('team_a_score'),'team_b_score'=>$this->request->getPost('team_b_score'),'judged'=>(array)$this->request->getPost('judged'),'notes'=>$this->request->getPost('notes')],(int)session()->get('user_id'));}
        catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
        return redirect()->back()->with('success','Unofficial result submitted for validation.');
    }
    public function validateResult(int $id){
        if(!$this->request->getPost('confirmed_sheet')) return redirect()->back()->with('error','Confirm comparison with the official score sheet/form before validation.');
        try{$this->repository()->validateResult($id,(int)session()->get('user_id'));}
        catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
        return redirect()->back()->with('success','Result validated and published as official.');
    }
}
