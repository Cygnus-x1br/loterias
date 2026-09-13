<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'share_code'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (! $user->share_code) {
                $user->share_code = self::generateUniqueShareCode();
            }
        });
    }

    public static function generateUniqueShareCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('share_code', $code)->exists());

        return $code;
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(Closing::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'user_id');
    }

    public function friendConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'friend_id');
    }

    // Retorna todos os amigos com status 'accepted'
    public function friends()
    {
        $connections = $this->connections()->where('status', 'accepted')->get();
        $friendConnections = $this->friendConnections()->where('status', 'accepted')->get();

        $friends = $connections->map->friend->merge($friendConnections->map->user);

        return $friends->unique('id');
    }

    public function sharedBets(): BelongsToMany
    {
        return $this->belongsToMany(Bet::class, 'bet_user');
    }

    public function sharedClosings(): BelongsToMany
    {
        return $this->belongsToMany(Closing::class, 'closing_user');
    }
}
