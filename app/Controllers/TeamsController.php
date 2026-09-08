<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Files\UploadedFile;

class TeamsController extends BaseController
{
    private const AVATAR_ERROR = 'Team avatar must be a WebP image (.webp) under 512 KB.';
    private const AVATAR_MAX_BYTES = 524288;
    private const AVATAR_MAX_DIMENSION = 1024;

    public function index()
    {
        return view('teams/index', ['title' => 'Teams', 'teams' => $this->repository()->teams()]);
    }

    public function store()
    {
        $payload = $this->teamPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        $avatar = $this->avatarPayload();
        if (isset($avatar['error'])) {
            return redirect()->back()->withInput()->with('error', $avatar['error']);
        }

        $avatarPath = null;
        try {
            if ($avatar['file'] instanceof UploadedFile) {
                $avatarPath = $this->moveAvatar($avatar['file']);
                $payload['avatar_path'] = $avatarPath;
            }
            $this->repository()->createTeam($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            if ($avatarPath !== null) {
                $this->deleteAvatarFile($avatarPath);
            }
            $message = $avatar['file'] instanceof UploadedFile && $avatarPath === null
                ? 'Team avatar could not be stored.'
                : 'Team name or code already exists.';
            return redirect()->back()->withInput()->with('error', $message);
        }
        return redirect()->back()->with('success', 'Team added.');
    }

    public function update(int $id)
    {
        $payload = $this->teamPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        $avatar = $this->avatarPayload();
        if (isset($avatar['error'])) {
            return redirect()->back()->with('error', $avatar['error']);
        }

        $removeAvatar = $this->postString('remove_avatar') !== '';
        $newAvatarPath = null;
        try {
            if ($avatar['file'] instanceof UploadedFile) {
                $newAvatarPath = $this->moveAvatar($avatar['file']);
                $payload['avatar_path'] = $newAvatarPath;
            } elseif ($removeAvatar) {
                $payload['avatar_path'] = null;
            }

            $oldAvatarPath = $this->repository()->updateTeam($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            if ($newAvatarPath !== null) {
                $this->deleteAvatarFile($newAvatarPath);
            }
            $message = $avatar['file'] instanceof UploadedFile && $newAvatarPath === null
                ? 'Team avatar could not be stored.'
                : 'Team could not be updated. Make sure its name and code are unique.';
            return redirect()->back()->with('error', $message);
        }

        if ($newAvatarPath !== null || $removeAvatar) {
            $this->deleteAvatarFile($oldAvatarPath);
        }
        return redirect()->back()->with('success', 'Team updated.');
    }

    public function delete(int $id)
    {
        try {
            $avatarPath = $this->repository()->deleteTeam($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The team operation could not be completed.'));
        }
        $this->deleteAvatarFile($avatarPath);
        return redirect()->back()->with('success', 'Team removed.');
    }

    private function teamPayload(): array
    {
        $name = trim($this->postString('name'));
        $code = strtoupper(trim($this->postString('code')));
        if ($name === '' || $code === '') {
            return ['error' => 'Team name and code are required.'];
        }
        if (strlen($name) > 150 || strlen($code) > 30) {
            return ['error' => 'Team name or code is too long.'];
        }
        return ['name' => $name, 'code' => $code];
    }

    private function avatarPayload(): array
    {
        $file = $this->request->getFile('avatar');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['file' => null];
        }
        if (! $file->isValid() || $file->hasMoved()) {
            return ['error' => self::AVATAR_ERROR];
        }
        if (strtolower($file->getClientExtension()) !== 'webp') {
            return ['error' => self::AVATAR_ERROR];
        }
        if (strtolower((string) $file->getMimeType()) !== 'image/webp') {
            return ['error' => self::AVATAR_ERROR];
        }
        if ($file->getSize() > self::AVATAR_MAX_BYTES) {
            return ['error' => self::AVATAR_ERROR];
        }

        $header = @file_get_contents($file->getTempName(), false, null, 0, 30);
        if (! is_string($header) || strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
            return ['error' => self::AVATAR_ERROR];
        }

        $dimensions = $this->webpDimensions($header);
        if ($dimensions !== null && ($dimensions[0] > self::AVATAR_MAX_DIMENSION || $dimensions[1] > self::AVATAR_MAX_DIMENSION)) {
            return ['error' => self::AVATAR_ERROR];
        }

        return ['file' => $file];
    }

    private function webpDimensions(string $header): ?array
    {
        if (strlen($header) < 30) {
            return null;
        }

        $chunk = substr($header, 12, 4);
        if ($chunk === 'VP8X') {
            $width = 1 + ord($header[24]) + (ord($header[25]) << 8) + (ord($header[26]) << 16);
            $height = 1 + ord($header[27]) + (ord($header[28]) << 8) + (ord($header[29]) << 16);
            return [$width, $height];
        }
        if ($chunk === 'VP8L' && ord($header[20]) === 0x2f) {
            $b1 = ord($header[21]);
            $b2 = ord($header[22]);
            $b3 = ord($header[23]);
            $b4 = ord($header[24]);
            $width = 1 + $b1 + (($b2 & 0x3f) << 8);
            $height = 1 + (($b2 & 0xc0) >> 6) + ($b3 << 2) + (($b4 & 0x0f) << 10);
            return [$width, $height];
        }
        if ($chunk === 'VP8 ' && substr($header, 23, 3) === "\x9d\x01\x2a") {
            $width = (ord($header[26]) | (ord($header[27]) << 8)) & 0x3fff;
            $height = (ord($header[28]) | (ord($header[29]) << 8)) & 0x3fff;
            return [$width, $height];
        }

        return null;
    }

    private function moveAvatar(UploadedFile $file): string
    {
        $directory = $this->avatarDirectory();
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Team avatar directory could not be created.');
        }

        $filename = bin2hex(random_bytes(16)) . '.webp';
        $file->move($directory, $filename);
        return 'uploads/team-avatars/' . $filename;
    }

    private function deleteAvatarFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return;
        }

        $directory = realpath($this->avatarDirectory());
        $file = realpath(rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));
        if ($directory === false || $file === false) {
            return;
        }

        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (! str_starts_with($file, $prefix) || ! is_file($file)) {
            return;
        }

        @unlink($file);
    }

    private function avatarDirectory(): string
    {
        return rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'team-avatars';
    }
}
