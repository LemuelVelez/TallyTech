<?php
namespace App\Controllers;
use App\Libraries\AuthSession;
class AccountController extends BaseController
{
    public function password(){return view('account/password',['title'=>'Change Password']);}
    public function updatePassword()
    {
        $userId=(int)session()->get('user_id');
        $current=$this->postString('current_password'); $new=$this->postString('new_password'); $confirm=$this->postString('confirm_password');
        $user=db_connect()->table('users')->where('id',$userId)->get()->getRowArray();
        if (! $user || ! password_verify($current,(string)$user['password_hash'])) return redirect()->back()->with('error','Current password is incorrect.');
        if ($new!==$confirm) return redirect()->back()->with('error','New password and confirmation do not match.');
        if (! preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/',$new)) return redirect()->back()->with('error','Password must be 8+ characters with uppercase, lowercase, number, and special character.');
        db_connect()->table('users')->where('id',$userId)->update(['password_hash'=>password_hash($new,PASSWORD_DEFAULT),'generated_password'=>null]);
        AuthSession::revokeUser($userId); AuthSession::revokeCurrent();
        return redirect()->to('/settings')->with('success','Password changed successfully.');
    }
}
