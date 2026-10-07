<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaFileStorage
{
    /** @param resource $stream */
    public function putStream(string $disk, string $path, $stream): bool
    {
        return Storage::disk($disk)->put($path, $stream);
    }

    public function put(string $disk, string $path, string $contents): bool
    {
        return Storage::disk($disk)->put($path, $contents);
    }

    public function delete(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    /** @return resource|false */
    public function readStream(string $disk, string $path)
    {
        return Storage::disk($disk)->readStream($path);
    }
}
