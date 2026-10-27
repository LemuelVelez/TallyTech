<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class ProfileController extends BaseController
{
    private const FIELDS = [
        'address'=>255,'phone'=>16,'facebook_url'=>255,'email'=>254,
        'college_office'=>180,'position'=>150,'emergency_contact_name'=>120,
        'emergency_contact_phone'=>16,'bio'=>3000,
    ];

    public function index()
    {
        $profile = $this->repository()->userProfile((int) session()->get('user_id'));
        if (! $profile) return redirect()->to('/dashboard')->with('error','Profile not found.');
        return view('account/profile', ['title'=>'Your Profile','profile'=>$profile,'editable'=>true]);
    }

    public function view(int $userId)
    {
        $viewerId = (int) session()->get('user_id');
        $role = (string) session()->get('role');
        if ($viewerId !== $userId
            && $role !== 'admin'
            && ! ($role === 'sports_coordinator' && $this->coordinatorCanManageTournamentManager($userId))) {
            return $this->response->setStatusCode(403)->setBody('Profile access denied.');
        }
        $profile = $this->repository()->userProfile($userId);
        if (! $profile) return $this->response->setStatusCode(404)->setBody('Profile not found.');
        return view('account/profile', ['title'=>'User Profile','profile'=>$profile,'editable'=>false]);
    }

    public function update(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $profile = $this->repository()->userProfile($userId);
        if (! $profile) return redirect()->back()->with('error','Profile not found.');
        $name = trim($this->postString('display_name'));
        if ($name === '' || mb_strlen($name) > 120) return redirect()->back()->withInput()->with('error','Enter a name of up to 120 characters.');
        $data = ['display_name'=>$name];
        foreach (self::FIELDS as $field=>$limit) {
            $value = trim($this->postString($field));
            if (mb_strlen($value) > $limit) return redirect()->back()->withInput()->with('error','The '.$field.' field is too long.');
            $data[$field] = $value === '' ? null : $value;
        }
        foreach (['phone','emergency_contact_phone'] as $field) {
            if ($data[$field] !== null && ! preg_match('/^(?:09\d{9}|\+639\d{9})$/', $data[$field]))
                return redirect()->back()->withInput()->with('error','Enter valid Philippine mobile numbers (09XXXXXXXXX or +639XXXXXXXXX).');
        }
        if ($data['email'] !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            return redirect()->back()->withInput()->with('error','Enter a valid email address.');
        if ($data['facebook_url'] !== null) {
            $parts = parse_url($data['facebook_url']);
            if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
                || ! in_array(strtolower($parts['host'] ?? ''),['facebook.com','m.facebook.com','fb.com'],true)
                || isset($parts['user']) || isset($parts['pass'])) {
                return redirect()->back()->withInput()->with('error','Enter an HTTPS Facebook URL on facebook.com, m.facebook.com or fb.com.');
            }
        }
        $oldPhoto = (string) ($profile['photo_path'] ?? '');
        $newPhoto = null;
        try {
            $photo = $this->request->getFile('photo');
            if ($photo && $photo->getError() !== UPLOAD_ERR_NO_FILE) {
                $newPhoto = $this->saveAvatar($photo);
                $data['photo_path'] = $newPhoto;
            } elseif ($this->postString('remove_photo') === '1') {
                $data['photo_path'] = null;
            } else {
                $data['photo_path'] = $oldPhoto ?: null;
            }
            $this->repository()->updateUserProfile($userId, $data);
            session()->set(['display_name'=>$name,'photo_path'=>(string)($data['photo_path']??'')]);
            if ($oldPhoto !== '' && $oldPhoto !== $data['photo_path']) $this->deleteAvatar($oldPhoto);
        } catch (\Throwable $error) {
            if ($newPhoto !== null) $this->deleteAvatar($newPhoto);
            return redirect()->back()->withInput()->with('error',$this->safeErrorMessage($error,'Profile could not be saved.'));
        }
        return redirect()->to('/profile')->with('success','Your profile has been updated.');
    }

    private function saveAvatar(\CodeIgniter\HTTP\Files\UploadedFile $file): string
    {
        if (! $file->isValid() || $file->getSize() > 2*1024*1024 || $file->getSize() < 1)
            throw new RuntimeException('Profile photos must be JPG, PNG or WebP and no larger than 2 MB.');
        $extension = strtolower((string) $file->getClientExtension());
        $expectedMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$extension] ?? null;
        if (! $expectedMime) throw new RuntimeException('Upload a JPG, PNG or WebP photo.');
        $path = $file->getTempName();
        $info = @getimagesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $head = file_get_contents($path,false,null,0,16);
        $signatureValid = match ($extension) {
            'jpg','jpeg' => str_starts_with($head,"\xFF\xD8\xFF"),
            'png' => str_starts_with($head,"\x89PNG\r\n\x1A\n"),
            'webp' => str_starts_with($head,'RIFF') && substr($head,8,4)==='WEBP',
            default => false,
        };
        if (! $signatureValid || ! $info || $info['mime'] !== $expectedMime || $mime !== $expectedMime
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000 || $info[0]*$info[1] > 12000000)
            throw new RuntimeException('The uploaded photo is not a valid image.');
        if (! function_exists('imagecreatefromjpeg')) throw new RuntimeException('Image processing is unavailable.');
        $source = match ($expectedMime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (! $source) throw new RuntimeException('The photo could not be processed.');
        $width = imagesx($source); $height = imagesy($source);
        $ratio = min(1,512/max($width,$height));
        $target = imagecreatetruecolor(max(1,(int)round($width*$ratio)),max(1,(int)round($height*$ratio)));
        imagealphablending($target,false); imagesavealpha($target,true);
        imagecopyresampled($target,$source,0,0,0,0,imagesx($target),imagesy($target),$width,$height);
        imagedestroy($source);
        $directory = FCPATH.'uploads/user-avatars/';
        if (! is_dir($directory) && ! @mkdir($directory,0775,true) && ! is_dir($directory)) {
            imagedestroy($target); throw new RuntimeException('Avatar storage is unavailable.');
        }
        $filename = bin2hex(random_bytes(16)).'.webp';
        $saved = @imagewebp($target,$directory.$filename,82);
        imagedestroy($target);
        if (! $saved) throw new RuntimeException('The photo could not be saved.');
        return 'uploads/user-avatars/'.$filename;
    }

    private function deleteAvatar(string $relativePath): void
    {
        if (preg_match('~^uploads/user-avatars/[a-f0-9]{32}\.webp$~',$relativePath)) {
            @unlink(FCPATH.$relativePath);
        }
    }
}
