<?php

namespace App\Controllers;

use App\Application\Services\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) return redirect()->to('/dashboard');
        return view('auth/login', ['title'=>'Sign in']);
    }

    public function attempt()
    {
        $user=(new AuthService($this->repository()))->authenticate((string)$this->request->getPost('username'),(string)$this->request->getPost('password'));
        if (! $user) return redirect()->back()->withInput()->with('error','Invalid username or password.');
        session()->regenerate();
        session()->set(['user_id'=>$user['id'],'username'=>$user['username'],'display_name'=>$user['display_name'],'role'=>$user['role']]);
        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
