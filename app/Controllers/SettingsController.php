<?php
namespace App\Controllers;
class SettingsController extends BaseController
{
    public function index(){return view('settings/index',['title'=>'Settings','settings'=>$this->repository()->getUserSettings((int)session()->get('user_id'))]);}
    public function update(){
        $this->repository()->updateUserSettings((int)session()->get('user_id'),['compact_sidebar'=>$this->request->getPost('compact_sidebar')?'1':'0','result_density'=>(string)($this->request->getPost('result_density')?:'comfortable')]);
        return redirect()->back()->with('success','Settings saved.');
    }
}
