<?php
namespace App\Controllers;
class TeamsController extends BaseController
{
    public function index(){ return view('teams/index',['title'=>'Teams','teams'=>$this->repository()->teams()]); }
    public function store(){
        $name=trim((string)$this->request->getPost('name')); $code=strtoupper(trim((string)$this->request->getPost('code')));
        if($name===''||$code==='') return redirect()->back()->with('error','Team name and code are required.');
        $this->repository()->createTeam(['name'=>$name,'code'=>$code,'created_at'=>date('Y-m-d H:i:s')],(int)session()->get('user_id'));
        return redirect()->back()->with('success','Team added.');
    }
    public function delete(int $id){
        try{$this->repository()->deleteTeam($id,(int)session()->get('user_id')); return redirect()->back()->with('success','Team removed.');}
        catch(\Throwable $e){return redirect()->back()->with('error','Team cannot be removed while it is used by schedules/results.');}
    }
}
