<?php

namespace App\Libraries;

class AuthSession
{
    public static function build(array $user): void
    {
        $db = db_connect();
        $settingsRows = $db->table('user_settings')->where('user_id', (int) $user['id'])->get()->getResultArray();
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $density = in_array(($settings['result_density'] ?? 'comfortable'), ['comfortable','compact'], true) ? $settings['result_density'] : 'comfortable';
        $theme = in_array(($settings['theme'] ?? 'system'), ['light','dark','system'], true) ? $settings['theme'] : 'system';
        $font = in_array(($settings['font_size'] ?? 'medium'), ['small','medium','large'], true) ? $settings['font_size'] : 'medium';
        $paper = in_array(($settings['paper_size'] ?? 'letter'), ['letter','a4','legal'], true) ? $settings['paper_size'] : 'letter';
        $orientation = in_array(($settings['orientation'] ?? 'portrait'), ['portrait','landscape'], true) ? $settings['orientation'] : 'portrait';
        session()->regenerate(true);
        session()->remove('compact_sidebar');
        session()->set([
            'user_id' => (int) $user['id'], 'username' => (string) $user['username'], 'display_name' => (string) $user['display_name'], 'role' => (string) $user['role'],
            'result_density' => $density, 'theme' => $theme, 'font_size' => $font, 'paper_size' => $paper, 'orientation' => $orientation,
            'include_team_ranking' => ($settings['include_team_ranking'] ?? '1') === '1' ? '1' : '0', 'include_filter_summary' => ($settings['include_filter_summary'] ?? '1') === '1' ? '1' : '0', 'show_timestamp' => ($settings['show_timestamp'] ?? '1') === '1' ? '1' : '0',
        ]);
        if (($settings['compact_sidebar'] ?? '0') === '1') session()->set('compact_sidebar', true);
    }

    public static function restoreFromRememberCookie(): bool
    {
        $cookie = (string) service('request')->getCookie('tt_remember');
        if (! str_contains($cookie, ':')) return false;
        [$selector, $secret] = array_pad(explode(':', $cookie, 2), 2, '');
        if (! preg_match('/^[a-f0-9]{24}$/', $selector) || ! preg_match('/^[a-f0-9]{64}$/', $secret)) return false;
        $db = db_connect();
        $row = $db->table('auth_remember_tokens t')->select('t.*,u.username,u.display_name,u.role,u.status')->join('users u', 'u.id=t.user_id')->where('t.selector', $selector)->get()->getRowArray();
        if (! $row || ($row['status'] ?? '') !== 'active' || strtotime((string) $row['expires_at']) <= time() || ! hash_equals((string) $row['token_hash'], hash('sha256', $secret))) {
            if ($row) $db->table('auth_remember_tokens')->where('id', $row['id'])->delete();
            return false;
        }
        self::build($row);
        self::rotate((int) $row['id'], $selector);
        return true;
    }

    public static function issue(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $secret = bin2hex(random_bytes(32));
        db_connect()->table('auth_remember_tokens')->insert(['user_id'=>$userId,'selector'=>$selector,'token_hash'=>hash('sha256',$secret),'expires_at'=>date('Y-m-d H:i:s', time()+2592000),'created_at'=>date('Y-m-d H:i:s')]);
        self::setCookie($selector . ':' . $secret, 2592000);
    }

    private static function rotate(int $id, string $selector): void
    {
        $secret = bin2hex(random_bytes(32));
        db_connect()->table('auth_remember_tokens')->where('id', $id)->update(['token_hash'=>hash('sha256',$secret),'expires_at'=>date('Y-m-d H:i:s', time()+2592000)]);
        self::setCookie($selector . ':' . $secret, 2592000);
    }

    public static function revokeCurrent(): void
    {
        $cookie = (string) service('request')->getCookie('tt_remember');
        if (str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            db_connect()->table('auth_remember_tokens')->where('selector', $selector)->delete();
        }
        self::setCookie('', -3600);
    }

    public static function revokeUser(int $userId): void
    {
        db_connect()->table('auth_remember_tokens')->where('user_id', $userId)->delete();
    }

    private static function setCookie(string $value, int $maxAge): void
    {
        service('response')->setCookie('tt_remember', $value, $maxAge, '', '/', '', service('request')->isSecure(), true, 'Lax');
    }
}
