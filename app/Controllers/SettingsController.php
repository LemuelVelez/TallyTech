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

    public function update()
    {
        $density = (string) ($this->request->getPost('result_density') ?: 'comfortable');
        if (! in_array($density, ['comfortable', 'compact'], true)) {
            return redirect()->back()->with('error', 'Select a valid display density.');
        }
        $settings = [
            'compact_sidebar' => $this->request->getPost('compact_sidebar') ? '1' : '0',
            'result_density' => $density,
        ];
        $this->repository()->updateUserSettings((int) session()->get('user_id'), $settings);
        session()->set([
            'compact_sidebar' => $settings['compact_sidebar'] === '1',
            'result_density' => $settings['result_density'],
        ]);
        return redirect()->back()->with('success', 'Settings saved.');
    }
}
