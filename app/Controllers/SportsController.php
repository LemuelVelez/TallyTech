<?php
namespace App\Controllers;
class SportsController extends BaseController
{
    public function index(){ $d=$this->scoringService()->commonData(); $d['title']='Sports'; return view('sports/index',$d); }
    public function store(){
        $event=$this->repository()->activeEvent(); if(!$event) return redirect()->back()->with('error','Add and activate an event first.');
        $name=trim((string)$this->request->getPost('name')); $category=(string)$this->request->getPost('category'); $type=(string)$this->request->getPost('result_type');
        if($name===''||!in_array($category,['Men','Women','Mixed'],true)||!in_array($type,['match','judged'],true)) return redirect()->back()->with('error','Complete all sport fields.');
        $this->repository()->createSport(['event_id'=>$event['id'],'name'=>$name,'category'=>$category,'result_type'=>$type,'created_at'=>date('Y-m-d H:i:s')],(int)session()->get('user_id'));
        return redirect()->back()->with('success','Sport added.');
    }
}
