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

        $rememberedAccount = AuthSession::rememberedAccount();
        return $this->response->setBody(view('auth/login', [
            'title' => 'Sign in',
            'rememberedAccount' => $rememberedAccount,
        ]));
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
        if ($this->postString('remember_me') === '1') {
            AuthSession::issue((int) $user['id']);
        } else {
            AuthSession::revokeCurrent();
        }

        return redirect()->to('/dashboard')->withCookies()->with('success', 'Signed in successfully.');
    }

    public function continueRemembered()
    {
        if (! AuthSession::restoreFromRememberCookie()) {
            return redirect()->to('/login')->withCookies()->with('error', 'This remembered sign-in is no longer available. Please sign in again.');
        }

        return redirect()->to('/dashboard')->withCookies()->with('success', 'Signed in successfully.');
    }

    public function forgetRemembered()
    {
        AuthSession::revokeCurrent();
        return redirect()->to('/login')->withCookies()->with('success', 'This device is no longer remembered.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login?logged_out=1');
    }
}
