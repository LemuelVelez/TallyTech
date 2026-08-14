<?php

namespace App\Controllers;

use App\Application\Services\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login', ['title' => 'Sign in']);
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $user = (new AuthService($this->repository()))->authenticate($username, $password);
        if (! $user) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password.');
        }

        session()->regenerate();
        $settings = $this->repository()->getUserSettings((int) $user['id']);
        session()->set([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'compact_sidebar' => ($settings['compact_sidebar'] ?? '0') === '1',
            'result_density' => in_array(($settings['result_density'] ?? 'comfortable'), ['comfortable', 'compact'], true)
                ? $settings['result_density']
                : 'comfortable',
        ]);
        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
