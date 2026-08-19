<?php

namespace App\Controllers;

class LocationsController extends BaseController
{
    public function index()
    {
        return view('locations/index', [
            'title' => 'Locations',
            'locations' => $this->repository()->allLocations(),
        ]);
    }

    public function store()
    {
        $name = trim($this->postString('name'));
        if ($name === '' || mb_strlen($name) > 150) {
            return redirect()->back()->withInput()->with('error', 'Location name is required and must be 150 characters or fewer.');
        }

        try {
            $this->repository()->createLocation($name, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The location could not be created.'));
        }

        return redirect()->back()->with('success', 'Location added.');
    }

    public function update(int $id)
    {
        $name = trim($this->postString('name'));
        if ($name === '' || mb_strlen($name) > 150) {
            return redirect()->back()->with('error', 'Location name is required and must be 150 characters or fewer.');
        }

        try {
            $this->repository()->updateLocation($id, $name, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The location could not be updated.'));
        }

        return redirect()->back()->with('success', 'Location updated.');
    }

    public function setStatus(int $id)
    {
        $isActive = $this->postString('is_active') === '1';

        try {
            $this->repository()->setLocationActive($id, $isActive, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The location status could not be changed.'));
        }

        return redirect()->back()->with('success', $isActive ? 'Location enabled.' : 'Location disabled.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteLocation($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The location could not be deleted.'));
        }

        return redirect()->back()->with('success', 'Location removed.');
    }
}
