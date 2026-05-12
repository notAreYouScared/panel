<?php

namespace App\Observers;

use App\Facades\Activity;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminActivityObserver
{
    /**
     * Tracks events already logged in this request to avoid duplicates.
     *
     * @var array<string, bool>
     */
    private static array $logged = [];

    /**
     * Determines if the current request is being handled by the admin panel.
     */
    private function isAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin';
    }

    /**
     * Logs an admin activity event for the given model, deduplicating within a single request.
     *
     * @param  string  $event  The event name (e.g. 'admin:user.create')
     * @param  Model  $model  The model being acted upon
     * @param  array<string, mixed>  $properties  Additional properties to log
     */
    private function log(string $event, Model $model, array $properties = []): void
    {
        if (!$this->isAdminPanel()) {
            return;
        }

        $actor = user();
        if (!$actor) {
            return;
        }

        // Deduplicate identical events for the same record within a single request.
        $key = $event . ':' . $model::class . ':' . $model->getKey();
        if (isset(static::$logged[$key])) {
            return;
        }
        static::$logged[$key] = true;

        $log = Activity::event($event)
            ->actor($actor)
            ->subject($model);

        foreach ($properties as $propKey => $propValue) {
            $log->property($propKey, $propValue);
        }

        $log->log();
    }

    public function created(Model $model): void
    {
        $this->log($this->eventFor($model, 'create'), $model, [
            'name' => $this->displayNameFor($model),
        ]);
    }

    public function updated(Model $model): void
    {
        $changedFields = $this->changedFieldsFor($model);
        $name = $this->displayNameFor($model);

        $this->log($this->eventFor($model, 'update'), $model, [
            'name' => empty($changedFields) ? $name : sprintf('%s (%s)', $name, implode(', ', $changedFields)),
            'count' => count($changedFields),
            'changes' => implode(', ', $changedFields),
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->log($this->eventFor($model, 'delete'), $model, [
            'name' => $this->displayNameFor($model),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function eventFor(Model $model, string $action): string
    {
        return sprintf('admin:%s.%s', $this->resourceNameFor($model), $action);
    }

    private function resourceNameFor(Model $model): string
    {
        if (defined($model::class . '::RESOURCE_NAME')) {
            /** @var string $resourceName */
            $resourceName = $model::RESOURCE_NAME;

            return $resourceName;
        }

        return Str::of(class_basename($model))->snake()->toString();
    }

    private function displayNameFor(Model $model): string
    {
        return match (true) {
            $model instanceof User => $model->username,
            $model instanceof Server,
            $model instanceof Node,
            $model instanceof Egg,
            $model instanceof Role => $model->name,
            default => (string) $model->getKey(),
        };
    }

    /**
     * Returns the sorted list of attribute names that changed on the given model,
     * excluding internal timestamps.
     *
     * @return string[]
     */
    private function changedFieldsFor(Model $model): array
    {
        $fields = collect(array_keys($model->getChanges()))
            ->reject(fn (string $field) => $field === 'updated_at')
            ->values()
            ->all();

        sort($fields);

        return $fields;
    }
}
