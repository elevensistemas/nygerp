<?php

namespace App\Models;

use App\Models\Supplier;
use App\Models\TrafficLooseStop;
use App\Models\ReciboChofer;
use App\Models\TransportistaPaymentMethod;
use App\Models\TransportistaLiquidationMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Transportista extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'business_name',
        'tax_id',
        'phone',
        'email',
        'license_number',
        'base_location',
        'status',
        'condition',
        'client_name',
        'phone_alt',
        'dni',
        'birth_date',
        'license_expires_at',
        'personal_insurance',
        'address_certificate',
        'criminal_record_certificate',
        'cbu',
        'bank_id',
        'account_number',
        'monotributo',
        'hire_date',
        'is_active',
        'portal_visibility',
        'notes',
        'supplier_id',
        'cost_efficiency',
        'performance_weight',
        'color',
        'advance_blocked_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'address_certificate' => 'boolean',
        'criminal_record_certificate' => 'boolean',
        'birth_date' => 'date',
        'license_expires_at' => 'date',
        'hire_date' => 'date',
        'advance_blocked_until' => 'date',
    ];

    public function transportes(): HasMany
    {
        return $this->hasMany(Transporte::class);
    }

    public function defaultTransporte()
    {
        return $this->transportes()->where('is_default', true)->first();
    }

    public function zones(): HasMany
    {
        return $this->hasMany(TransportistaZone::class);
    }

    public function sharedZones(): BelongsToMany
    {
        return $this->belongsToMany(TrafficZone::class, 'traffic_zone_transportista')->withTimestamps();
    }

    public function allZones(): Collection
    {
        $specific = $this->relationLoaded('zones') ? $this->zones : $this->zones()->get();
        $shared = $this->relationLoaded('sharedZones') ? $this->sharedZones : $this->sharedZones()->get();
        return $specific->concat($shared)->values();
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TrafficRoute::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(TransportistaPaymentMethod::class);
    }

    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(TransportistaPaymentMethod::class)->where('is_default', true);
    }

    public function recibosChofer(): HasMany
    {
        return $this->hasMany(ReciboChofer::class);
    }

    public function paymentFleet(): BelongsToMany
    {
        return $this->belongsToMany(DriverPaymentFleet::class, 'driver_payment_fleet_transportista')
            ->withTimestamps();
    }

    public function liquidationMeta(): HasOne
    {
        return $this->hasOne(TransportistaLiquidationMeta::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Transportista $carrier) {
            $carrier->routes()->pluck('id')->each(fn ($routeId) => TrafficLooseStop::releaseFromRoute($routeId));
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(DriverAdvanceRequest::class, 'transportista_id');
    }

    public function isAdvanceBlocked(): bool
    {
        return $this->advance_blocked_until !== null && $this->advance_blocked_until->isAfter(now()->subDay());
    }

    public function hasAdvanceThisMonth(): bool
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return $this->advanceRequests()
            ->whereBetween('fecha_pedido', [$start, $end])
            ->exists();
    }

    public function getDaysSinceHiredAttribute(): int
    {
        $baseDate = $this->hire_date ?? $this->created_at;
        if (!$baseDate) {
            return 9999;
        }
        return (int) $baseDate->diffInDays(now()->startOfDay(), false);
    }

    public function isNewDriver(?int $daysThreshold = null): bool
    {
        if ($daysThreshold === null) {
            static $cachedDays = null;
            if ($cachedDays === null) {
                $setting = \App\Models\DriverImportSetting::where('setting_key', 'driver_new_days')->first();
                $cachedDays = $setting ? (int) $setting->setting_value : 30;
            }
            $daysThreshold = $cachedDays;
        }

        $days = $this->days_since_hired;
        return $days >= 0 && $days <= $daysThreshold;
    }
}
