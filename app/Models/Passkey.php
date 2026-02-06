<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Passkey
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string $credential_id
 * @property string $public_key_data
 * @property string|null $aaguid
 * @property string|null $attestation_type
 * @property array|null $transports
 * @property int $counter
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 */
class Passkey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'public_key_data',
        'aaguid',
        'attestation_type',
        'transports',
        'counter',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'counter' => 'integer',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateCounter(int $counter): void
    {
        $this->update([
            'counter' => $counter,
            'last_used_at' => now(),
        ]);
    }
}
