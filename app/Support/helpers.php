<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('storage_image_url')) {
    /**
     * Generate a public URL for an image stored on the configured "public" disk,
     * falling back to the raw public path when the symlink is missing.
     */
    function storage_image_url(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $storageLink = public_path('storage');

        if ($storageLink && is_dir($storageLink) && file_exists($storageLink . '/' . $cleanPath)) {
            return asset("storage/{$cleanPath}");
        }

        if (file_exists(public_path($cleanPath))) {
            return asset($cleanPath);
        }

        return Storage::disk('public')->url($cleanPath);
    }
}
