<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class SystemParameter extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'value',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(SystemParameterLog::class);
    }

    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public static function value(string $key, ?string $default = null): ?string
    {
        $cacheKey = "system_parameter:{$key}";
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($key, $default) {
            $parameter = static::forKey($key);
            return $parameter ? $parameter->value : $default;
        });
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::value($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 't', 'yes', 'y'], true);
    }

    public function refreshCache(): void
    {
        Cache::forget("system_parameter:{$this->key}");
    }
}
