<?php
namespace App\Filters;
use App\Libraries\AuthSession;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('user_id') && ! AuthSession::restoreFromRememberCookie()) return redirect()->to('/login')->withCookies()->with('error', 'Please sign in to continue.');
        try { $user=db_connect()->table('users')->select('id,username,display_name,role,status')->where('id',(int)session()->get('user_id'))->get()->getRowArray(); }
        catch (\Throwable $e) { session()->destroy(); return redirect()->to('/login')->withCookies()->with('error','Your session could not be verified. Please sign in again.'); }
        if (! $user || ($user['status']??'')!=='active') { AuthSession::revokeCurrent(); session()->destroy(); return redirect()->to('/login')->withCookies()->with('error','Your account is inactive or no longer available.'); }
        session()->set(['display_name'=>$user['display_name'],'role'=>$user['role']]);
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null){if(method_exists($response,'withCookies'))$response->withCookies();$response->setHeader('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');$response->setHeader('Pragma','no-cache');$response->setHeader('Expires','0');}
}
