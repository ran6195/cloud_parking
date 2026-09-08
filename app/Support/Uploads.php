<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Uploads
{
    public static function disk(): string
    {
        return config('filament.default_filesystem_disk', 'public');
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk(self::disk())->url($path);
    }
}
