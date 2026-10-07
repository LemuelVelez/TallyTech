<?php

namespace App\Controllers;

class SettingsController extends BaseController
{
    public function index()
    {
        return view('settings/index', [
            'title' => 'Settings',
            'settings' => $this->repository()->getUserSettings((int) session()->get('user_id')),
        ]);
    }

    public function updateTheme()
    {
        $theme = $this->postString('theme');
        $acceptsJson = $this->request->isAJAX() || str_contains(strtolower($this->request->getHeaderLine('Accept')), 'application/json');

        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            if ($acceptsJson) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok' => false,
                    'message' => 'Select a valid theme.',
                    'csrf_hash' => csrf_hash(),
                ]);
            }

            return redirect()->back()->with('error', 'Select a valid theme.');
        }

        try {
            $this->repository()->updateUserSettings((int) session()->get('user_id'), ['theme' => $theme]);
            session()->set('theme', $theme);
        } catch (\Throwable $e) {
            if ($acceptsJson) {
                return $this->response->setStatusCode(500)->setJSON([
                    'ok' => false,
                    'message' => 'Theme could not be saved.',
                    'csrf_hash' => csrf_hash(),
                ]);
            }

            return redirect()->back()->with('error', 'Theme could not be saved.');
        }

        if ($acceptsJson) {
            return $this->response->setJSON([
                'ok' => true,
                'theme' => $theme,
                'csrf_hash' => csrf_hash(),
            ]);
        }

        return redirect()->back()->with('success', 'Theme updated.');
    }

    public function update()
    {
        $section = $this->postString('section') ?: 'display';
        if (! in_array($section, ['display', 'report'], true)) {
            return redirect()->back()->with('error', 'Select a valid settings section.');
        }

        if ($section === 'display') {
            $density = $this->postString('result_density') ?: 'comfortable';
            $theme = $this->postString('theme') ?: 'system';
            $fontSize = $this->postString('font_size') ?: 'medium';
            $compactSidebar = $this->postString('compact_sidebar');
            if (! in_array($density, ['comfortable', 'compact'], true)) {
                return redirect()->back()->with('error', 'Select a valid display density.');
            }
            if (! in_array($theme, ['light', 'dark', 'system'], true)) {
                return redirect()->back()->with('error', 'Select a valid theme.');
            }
            if (! in_array($fontSize, ['small', 'medium', 'large'], true)) {
                return redirect()->back()->with('error', 'Select a valid font size.');
            }
            if (! in_array($compactSidebar, ['', '1'], true)) {
                return redirect()->back()->with('error', 'Select a valid compact navigation preference.');
            }
            $settings = [
                'compact_sidebar' => $compactSidebar === '1' ? '1' : '0',
                'result_density' => $density,
                'theme' => $theme,
                'font_size' => $fontSize,
            ];
        } else {
            $paperSize = $this->postString('paper_size') ?: 'letter';
            $orientation = $this->postString('orientation') ?: 'portrait';
            $includeTeamRanking = $this->postString('include_team_ranking');
            $includeFilterSummary = $this->postString('include_filter_summary');
            $showTimestamp = $this->postString('show_timestamp');
            if (! in_array($paperSize, ['letter', 'a4', 'legal'], true)) {
                return redirect()->back()->with('error', 'Select a valid paper size.');
            }
            if (! in_array($orientation, ['portrait', 'landscape'], true)) {
                return redirect()->back()->with('error', 'Select a valid print orientation.');
            }
            foreach ([$includeTeamRanking, $includeFilterSummary, $showTimestamp] as $toggle) {
                if (! in_array($toggle, ['', '1'], true)) {
                    return redirect()->back()->with('error', 'Select valid report and print defaults.');
                }
            }
            $settings = [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'include_team_ranking' => $includeTeamRanking === '1' ? '1' : '0',
                'include_filter_summary' => $includeFilterSummary === '1' ? '1' : '0',
                'show_timestamp' => $showTimestamp === '1' ? '1' : '0',
            ];
        }

        try {
            $this->repository()->updateUserSettings((int) session()->get('user_id'), $settings);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Settings could not be saved.');
        }

        foreach ($settings as $key => $value) {
            session()->set($key, $value);
        }
        if (($settings['compact_sidebar'] ?? null) === '1') {
            session()->set('compact_sidebar', true);
        } elseif (array_key_exists('compact_sidebar', $settings)) {
            session()->remove('compact_sidebar');
        }

        return redirect()->back()->with('success', $section === 'report' ? 'Report and print defaults saved.' : 'Display settings saved.');
    }
}
