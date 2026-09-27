<?php

namespace App\Controllers;

use App\Application\Services\AuthService;
use App\Libraries\AuthSession;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) return redirect()->to('/dashboard');
        if ($this->request->getGet('logged_out') === '1') {
            session()->setFlashdata('success', 'Logged out successfully.');
            return redirect()->to('/login');
        }
        return view('auth/login', ['title' => 'Sign in']);
    }

    public function attempt()
    {
        $username = trim($this->postString('username'));
        $password = $this->postString('password');
        try {
            $user = (new AuthService($this->repository()))->authenticate($username, $password);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Sign-in is temporarily unavailable. Please try again.')->with('login_username', $username);
        }
        if (! $user) return redirect()->back()->with('error', 'Invalid username or password.')->with('login_username', $username);
        AuthSession::build($user);
        if ($this->postString('remember_me') === '1') AuthSession::issue((int) $user['id']);
        return redirect()->to('/dashboard')->with('success', 'Signed in successfully.');
    }

    public function logout()
    {
        AuthSession::revokeCurrent();
        session()->destroy();
        return redirect()->to('/login?logged_out=1');
    }
}
