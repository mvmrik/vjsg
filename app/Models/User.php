<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'public_key',
        // store hashed private_key; raw private key should not be mass assignable
        'last_active',
        'locale',
        'balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'private_key',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_active' => 'datetime',
        'balance' => 'integer',
    ];

    /**
     * Generate unique keys for the user
     */
    public static function generateKeys()
    {
        do {
            $privateKey = bin2hex(random_bytes(32)); // 64 character hex string
            $publicKey = bin2hex(random_bytes(32));  // 64 character hex string
            // Use sha256 lookup and optionally HMAC for uniqueness
            $sha = hash('sha256', $privateKey);
            $lookup = $sha;
            $secret = env('PRIVATE_KEY_LOOKUP_KEY');
            if (!empty($secret)) {
                $lookup = hash_hmac('sha256', $sha, $secret);
            }
        } while (
            static::where('private_key', $lookup)->exists() || 
            static::where('public_key', $publicKey)->exists()
        );
        
        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey
        ];
    }

    /**
     * Compute private key sha256 (for lookup)
     */
    public static function privateKeySha256(string $privateKey): string
    {
        return hash('sha256', $privateKey);
    }

    /**
     * Compute lookup value for private key depending on env configuration.
     * If PRIVATE_KEY_LOOKUP_KEY is set, we compute HMAC over sha256(privateKey).
     * Otherwise we fallback to plain sha256.
     */
    public static function computeLookup(string $privateKey): string
    {
        $sha = static::privateKeySha256($privateKey);
        $secret = env('PRIVATE_KEY_LOOKUP_KEY');
        if (!empty($secret)) {
            return hash_hmac('sha256', $sha, $secret);
        }
        return $sha;
    }

    /**
     * Remember tokens relationship (multi-device remember)
     */
    public function rememberTokens()
    {
        return $this->hasMany(UserRememberToken::class);
    }
}
