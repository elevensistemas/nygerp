<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Transportista;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public const ROLE_SUPER = 'super';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_USER = 'user';
    public const ROLE_TRANSPORTISTA = 'transportista';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'confirmation_token',
        'confirmation_sent_at',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
        'transportista_id',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function needsAcceptance(): bool
    {
        return $this->accepted_at === null;
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->accepted_at !== null;
    }

    public function markPending(string $token): void
    {
        $this->confirmation_token = $token;
        $this->confirmation_sent_at = now();
        $this->accepted_at = null;
        $this->accepted_ip = null;
        $this->accepted_user_agent = null;
        $this->save();
    }

    public function markAccepted(array $data = []): void
    {
        $this->accepted_at = now();
        $this->confirmation_token = null;
        if (isset($data['ip_address'])) {
            $this->accepted_ip = $data['ip_address'];
        }
        if (isset($data['user_agent'])) {
            $this->accepted_user_agent = $data['user_agent'];
        }
        $this->email_verified_at = $this->email_verified_at ?? now();
        $this->save();
    }

    public function newConfirmationToken(): string
    {
        return Str::random(48);
    }

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER,
            self::ROLE_ADMIN,
            self::ROLE_VIEWER,
            self::ROLE_TRANSPORTISTA,
            self::ROLE_USER,
        ];
    }

    public static function roleLabels(): array
    {
        return [
            self::ROLE_SUPER => 'Super',
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_VIEWER => 'Solo lectura',
            self::ROLE_TRANSPORTISTA => 'Transportista',
            self::ROLE_USER => 'Usuario',
        ];
    }

    public function isSuper(): bool
    {
        return $this->normalizedRole() === self::ROLE_SUPER;
    }

    public function isAdmin(): bool
    {
        return $this->normalizedRole() === self::ROLE_ADMIN;
    }

    public function isAdminOrSuper(): bool
    {
        return $this->isSuper() || $this->isAdmin();
    }

    public function isReadOnly(): bool
    {
        return $this->normalizedRole() === self::ROLE_VIEWER;
    }

    public function normalizedRole(): string
    {
        return $this->attributes['role']
            ?? ($this->id === 1 ? self::ROLE_ADMIN : self::ROLE_USER);
    }

    public function isTransportista(): bool
    {
        return $this->normalizedRole() === self::ROLE_TRANSPORTISTA;
    }

    public function transportistaProfile(): HasOne
    {
        return $this->hasOne(Transportista::class, 'user_id');
    }

    public function assignedTransportistaId(): ?int
    {
        return $this->transportista_id ?? optional($this->transportistaProfile)->id;
    }
}
