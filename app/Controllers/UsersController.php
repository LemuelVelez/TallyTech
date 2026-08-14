<?php
namespace App\Controllers;
class UsersController extends BaseController
{
    public function sportsManagers(){ $e=$this->repository()->activeEvent(); return view('users/index',['title'=>'Sports Managers','roleType'=>'manager','users'=>$this->repository()->usersByRole('manager'),'sports'=>$this->repository()->sports((int)($e['id']??0))]); }
    public function facilitators(){ $e=$this->repository()->activeEvent(); return view('users/index',['title'=>'Facilitators','roleType'=>'facilitator','users'=>$this->repository()->usersByRole('facilitator'),'sports'=>$this->repository()->sports((int)($e['id']??0))]); }
    public function storeSportsManager(){return $this->storeRole('manager');}
    public function storeFacilitator(){return $this->storeRole('facilitator');}
    private function storeRole(string $role){
        $username=trim((string)$this->request->getPost('username')); $name=trim((string)$this->request->getPost('display_name')); $password=(string)$this->request->getPost('password');
        if(strlen($username)<3||$name==='') return redirect()->back()->with('error','Username and name are required.');
        if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/',$password)) return redirect()->back()->with('error','Password must be 8+ characters with uppercase, lowercase, number, and special character.');
        $sportIds=$role==='facilitator'?(array)$this->request->getPost('sport_ids'):[];
        if($role==='facilitator'&&!array_filter($sportIds)) return redirect()->back()->with('error','Assign at least one sport to the facilitator.');
        try{$this->repository()->createUser(['username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'display_name'=>$name,'role'=>$role,'status'=>'active','created_at'=>date('Y-m-d H:i:s')],$sportIds,(int)session()->get('user_id'));}
        catch(\Throwable $e){return redirect()->back()->with('error','Username already exists or the account could not be created.');}
        return redirect()->back()->with('success',ucfirst($role).' account added.');
    }
}
