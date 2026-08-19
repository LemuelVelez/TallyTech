<?php

namespace App\Controllers;

class SportCategoriesController extends BaseController
{
    public function index()
    {
        return view('sport_categories/index', [
            'title' => 'Sport Categories',
            'categories' => $this->repository()->sportCategories(true),
        ]);
    }

    public function store()
    {
        $name = trim($this->postString('name'));
        if ($name === '' || mb_strlen($name) > 80) {
            return redirect()->back()->withInput()->with('error', 'Category name is required and must be 80 characters or fewer.');
        }

        try {
            $this->repository()->createSportCategory($name, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The sport category could not be created.'));
        }

        return redirect()->back()->with('success', 'Sport category added.');
    }

    public function update(int $id)
    {
        $name = trim($this->postString('name'));
        if ($name === '' || mb_strlen($name) > 80) {
            return redirect()->back()->with('error', 'Category name is required and must be 80 characters or fewer.');
        }

        try {
            $this->repository()->updateSportCategory($id, $name, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The sport category could not be updated.'));
        }

        return redirect()->back()->with('success', 'Sport category updated.');
    }

    public function setStatus(int $id)
    {
        $isActive = $this->postString('is_active') === '1';

        try {
            $this->repository()->setSportCategoryActive($id, $isActive, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The sport category status could not be changed.'));
        }

        return redirect()->back()->with('success', $isActive ? 'Sport category enabled.' : 'Sport category disabled.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteSportCategory($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The sport category could not be deleted.'));
        }

        return redirect()->back()->with('success', 'Sport category removed.');
    }
}
