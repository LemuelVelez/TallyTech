<?php
namespace App\Controllers;
class WeightedPointsController extends BaseController
{
    public function index(){ $e=$this->repository()->activeEvent(); return view('weighted_points/index',['title'=>'Weighted Points','activeEvent'=>$e,'sports'=>$this->repository()->sports((int)($e['id']??0)),'weightedPoints'=>$this->repository()->weightedPoints((int)($e['id']??0))]); }
    public function store(){
        if(session()->get('role')!=='manager') return redirect()->back()->with('error','Only tournament managers can submit weighted points.');
        $e=$this->repository()->activeEvent(); if(!$e) return redirect()->back()->with('error','No active event.');
        $sportId=(int)$this->request->getPost('sport_id'); if(!$sportId) return redirect()->back()->with('error','Select a sport.');
        $this->repository()->saveWeightedPoints(['event_id'=>$e['id'],'sport_id'=>$sportId,'first_points'=>(float)$this->request->getPost('first_points'),'second_points'=>(float)$this->request->getPost('second_points'),'third_points'=>(float)$this->request->getPost('third_points'),'participation_points'=>(float)$this->request->getPost('participation_points'),'submitted_at'=>date('Y-m-d H:i:s')],(int)session()->get('user_id'));
        return redirect()->back()->with('success','Weighted points submitted for validation.');
    }
    public function validatePoints(int $id){
        if(session()->get('role')!=='validator') return redirect()->back()->with('error','Only validators can validate weighted points.');
        $this->repository()->validateWeightedPoints($id,(int)session()->get('user_id')); return redirect()->back()->with('success','Weighted points validated.');
    }
}
