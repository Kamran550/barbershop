<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Şəkillərin təhlükəsiz saxlanması.
 *  - Fayl adı təsadüfi yaradılır (hashName); istifadəçinin verdiyi ad heç vaxt istifadə olunmur.
 *  - Uzantı faylın MƏZMUNUNDAN təyin olunur, client-in dediyi MIME-dən yox.
 *  - Tip/ölçü yoxlaması Form Request-dədir (image|mimes:jpg,jpeg,png,webp|max:2048). SVG qəbul edilmir (XSS riski).
 */
class ImageStorage
{
    private const DISK = 'public';

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /** Köhnə şəkli silib yenisini saxlayır. */
    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $path = $this->store($file, $directory);
        $this->delete($oldPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }
}
