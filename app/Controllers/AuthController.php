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

        $resultDensity = (string) ($settings['result_density'] ?? 'comfortable');
        $theme = (string) ($settings['theme'] ?? 'system');
        $fontSize = (string) ($settings['font_size'] ?? 'medium');
        $paperSize = (string) ($settings['paper_size'] ?? 'letter');
        $orientation = (string) ($settings['orientation'] ?? 'portrait');
        $resultDensity = in_array($resultDensity, ['comfortable', 'compact'], true) ? $resultDensity : 'comfortable';
        $theme = in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'system';
        $fontSize = in_array($fontSize, ['small', 'medium', 'large'], true) ? $fontSize : 'medium';
        $paperSize = in_array($paperSize, ['letter', 'a4', 'legal'], true) ? $paperSize : 'letter';
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

        session()->regenerate(true);
        session()->remove('compact_sidebar');
        session()->set([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'result_density' => $resultDensity,
            'theme' => $theme,
            'font_size' => $fontSize,
            'paper_size' => $paperSize,
            'orientation' => $orientation,
            'include_team_ranking' => ($settings['include_team_ranking'] ?? '1') === '1' ? '1' : '0',
            'include_filter_summary' => ($settings['include_filter_summary'] ?? '1') === '1' ? '1' : '0',
            'show_timestamp' => ($settings['show_timestamp'] ?? '1') === '1' ? '1' : '0',
        ]);
        if (($settings['compact_sidebar'] ?? '0') === '1') {
            session()->set('compact_sidebar', true);
        }
        return redirect()->to('/dashboard')->with('success', 'Signed in successfully.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login?logged_out=1');
    }
}
