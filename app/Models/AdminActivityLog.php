<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use LogicException;

/**
 * \App\Models\AdminActivityLog.
 *
 * @property int $id
 * @property string $event
 * @property string $ip
 * @property string|null $description
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Collection|null $properties
 * @property Collection|null $metadata
 * @property \Carbon\Carbon $timestamp
 * @property Model|\Eloquent $actor
 * @property Model|\Eloquent $subject
 *
 * @method static Builder|AdminActivityLog forActor(Model $actor)
 * @method static Builder|AdminActivityLog forEvent(string $action)
 * @method static Builder|AdminActivityLog forSubject(Model $subject)
 * @method static Builder|AdminActivityLog newModelQuery()
 * @method static Builder|AdminActivityLog newQuery()
 * @method static Builder|AdminActivityLog query()
 */
class AdminActivityLog extends Model implements HasIcon, HasLabel
{
    use MassPrunable;

    public const RESOURCE_NAME = 'admin_activity_log';

    public $timestamps = false;

    protected $guarded = [
        'id',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'collection',
            'metadata' => 'collection',
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

    /**
     * Scopes a query to only return results where the subject is a given model.
     */
    public function scopeForSubject(Builder $builder, Model $subject): Builder
    {
        return $builder->whereMorphedTo('subject', $subject);
    }

    public function prunable(): Builder
    {
        if (is_null(config('activity.admin_prune_days'))) {
            throw new LogicException('Cannot prune admin activity logs: no "admin_prune_days" configuration value is set.');
        }

        return static::where('timestamp', '<=', Carbon::now()->subDays(config('activity.admin_prune_days')));
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->timestamp = Carbon::now();
        });

        // Prevent deletion of admin activity logs
        static::deleting(function (self $model) {
            throw new LogicException('Admin activity logs cannot be deleted.');
        });
    }

    public function getIcon(): string
    {
        if ($this->actor instanceof User) {
            return 'tabler-user-shield';
        }

        return 'tabler-shield';
    }

    public function getLabel(): string
    {
        $properties = $this->wrapProperties();
        
        $translationKey = 'admin/activity.'.str($this->event)->replace(':', '.');
        $translated = trans_choice($translationKey, array_key_exists('count', $properties) ? $properties['count'] : 1, $properties);
        
        // If translation is missing, return the event name as fallback
        if ($translated === $translationKey) {
            return str($this->event)->title()->replace(':', ' ')->replace('-', ' ')->toString();
        }
        
        return $translated;
    }

    /**
     * @return array<string, string>
     */
    public function wrapProperties(): array
    {
        if (!$this->properties || $this->properties->isEmpty()) {
            return [];
        }

        $properties = $this->properties->mapWithKeys(function ($value, $key) {
            if (!is_array($value)) {
                return [$key => str($value)->stripTags()->toString()];
            }

            return [$key => str($value[0] ?? '')->stripTags()->toString(), "{$key}_count" => count($value)];
        });

        return $properties->toArray();
    }

    /**
     * Check if there's additional metadata to display.
     */
    public function hasAdditionalMetadata(): bool
    {
        return !is_null($this->metadata) && !$this->metadata->isEmpty();
    }
}
