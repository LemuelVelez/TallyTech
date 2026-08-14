<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) session()->get('user_id');
        if (! $userId) {
            return redirect()->to('/login')->with('error', 'Please sign in to continue.');
        }

        try {
            $user = db_connect()->table('users')->select('id,display_name,role,status')->where('id', $userId)->get()->getRowArray();
        } catch (\Throwable $e) {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Your session could not be verified. Please sign in again.');
        }

        if (! $user || ($user['status'] ?? '') !== 'active') {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Your account is inactive or no longer available.');
        }

        session()->set([
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
