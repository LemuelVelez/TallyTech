<?php
namespace App\Filters;
use App\Libraries\AuthSession;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('user_id') && ! AuthSession::restoreFromRememberCookie()) return redirect()->to('/login')->withCookies()->with('error','Please sign in to continue.');
        try { $user=db_connect()->table('users')->select('role,status')->where('id',(int)session()->get('user_id'))->get()->getRowArray(); }
        catch (\Throwable $e) { session()->destroy(); return redirect()->to('/login')->withCookies()->with('error','Your session could not be verified. Please sign in again.'); }
        if (! $user || ($user['status']??'')!=='active') { AuthSession::revokeCurrent(); session()->destroy(); return redirect()->to('/login')->withCookies()->with('error','Your account is inactive or no longer available.'); }
        $role=(string)($user['role']??''); session()->set('role',$role);
        if (! $role || ! in_array($role,$arguments??[],true)) return redirect()->to('/dashboard')->withCookies()->with('error','You do not have access to that page.');
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null){if(method_exists($response,'withCookies'))$response->withCookies();}
}
