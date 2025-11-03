<?php

namespace App\Models;

use App\Traits\HasValidation;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * \App\Models\AdminActivityLog.
 *
 * @property int $id
 * @property string $event
 * @property string $ip
 * @property string|null $description
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property Collection|null $properties
 * @property \Carbon\Carbon $timestamp
 * @property Model|\Eloquent $actor
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Model|\Eloquent|null $subject
 */
class AdminActivityLog extends Model implements HasIcon, HasLabel
{
    use HasValidation;

    public const RESOURCE_NAME = 'admin_activity_log';

    public $timestamps = false;

    protected $guarded = [
        'id',
        'timestamp',
    ];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'event' => ['required', 'string'],
        'ip' => ['required', 'string'],
        'description' => ['nullable', 'string'],
        'properties' => ['array'],
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'collection',
            'timestamp' => 'datetime',
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo()->withTrashed()->withoutGlobalScopes();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed()->withoutGlobalScopes();
    }

    public function scopeForEvent(Builder $builder, string $action): Builder
    {
        return $builder->where('event', $action);
    }

    /**
     * Scopes a query to only return results where the actor is a given model.
     */
    public function scopeForActor(Builder $builder, Model $actor): Builder
    {
        return $builder->whereMorphedTo('actor', $actor);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->timestamp = Carbon::now();
        });
    }

    public function getIcon(): string
    {
        if ($this->actor instanceof User) {
            return 'tabler-user';
        }

        return $this->actor_id === null ? 'tabler-device-desktop' : 'tabler-user-off';
    }

    public function getLabel(): string
    {
        return $this->description ?? $this->event;
    }

    public function getIp(): ?string
    {
        return user()?->can('view adminActivityLog') ? $this->ip : null;
    }

    public function htmlable(): string
    {
        $user = $this->actor;
        if (!$user instanceof User) {
            $user = new User([
                'email' => 'system@pelican.dev',
                'username' => 'system',
            ]);
        }

        $avatarUrl = Filament::getUserAvatarUrl($user);
        $username = str($user->username)->stripTags();
        $ip = $this->getIp();
        $ip = $ip ? $ip . ' — ' : '';

        return "
            <div style='display: flex; align-items: center;'>
                <img width='50px' height='50px' src='{$avatarUrl}' style='margin-right: 15px' />

                <div>
                    <p>$username — $this->event</p>
                    <p>{$this->getLabel()}</p>
                    <p>$ip<span title='{$this->timestamp->format('M j, Y g:ia')}'>{$this->timestamp->diffForHumans()}</span></p>
                </div>
            </div>
        ";
    }
}
