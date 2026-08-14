<?php
namespace App\Controllers;
class EventsController extends BaseController
{
    public function index(){return view('events/index',['title'=>'Events','events'=>$this->repository()->events()]);}
    public function store(){
        $name=trim((string)$this->request->getPost('name')); $year=(int)$this->request->getPost('year');
        if($name===''||$year<2000) return redirect()->back()->with('error','Enter a valid event and year.');
        $this->repository()->createEvent(['name'=>$name,'year'=>$year,'start_date'=>$this->request->getPost('start_date')?:null,'end_date'=>$this->request->getPost('end_date')?:null,'status'=>'active','is_active'=>$this->request->getPost('is_active')?1:0,'created_at'=>date('Y-m-d H:i:s')],(int)session()->get('user_id'));
        return redirect()->back()->with('success','Event added.');
    }
    public function activate(int $id){$this->repository()->activateEvent($id,(int)session()->get('user_id'));return redirect()->back()->with('success','Active event updated.');}
}
