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
        $username = trim($this->postString('username'));
        $password = $this->postString('password');
        try {
            $repository = $this->repository();
            $user = (new AuthService($repository))->authenticate($username, $password);
            $settings = $user ? $repository->getUserSettings((int) $user['id']) : [];
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Sign-in is temporarily unavailable. Please try again.')
                ->with('login_username', $username);
        }
        if (! $user) {
            return redirect()->back()
                ->with('error', 'Invalid username or password.')
                ->with('login_username', $username);
        }

        $resultDensity = $settings['result_density'] ?? 'comfortable';

        session()->regenerate(true);
        session()->set([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'compact_sidebar' => ($settings['compact_sidebar'] ?? '0') === '1',
            'result_density' => in_array($resultDensity, ['comfortable', 'compact'], true)
                ? $resultDensity
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
