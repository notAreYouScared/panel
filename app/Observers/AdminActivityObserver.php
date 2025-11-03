<?php

namespace App\Observers;

use App\Facades\AdminActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminActivityObserver
{
    protected static array $ignoredModels = [
        \App\Models\ActivityLog::class,
        \App\Models\AdminActivityLog::class,
        \App\Models\ActivityLogSubject::class,
    ];

    public function created(Model $model): void
    {
        if ($this->shouldLog($model)) {
            $this->logActivity('created', $model);
        }
    }

    public function updated(Model $model): void
    {
        if ($this->shouldLog($model)) {
            $this->logActivity('updated', $model, $model->getDirty());
        }
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldLog($model)) {
            $this->logActivity('deleted', $model);
        }
    }

    protected function shouldLog(Model $model): bool
    {
        // Don't log if we're not in the admin panel context
        if (!Auth::check() || !$this->isAdminContext()) {
            return false;
        }

        // Don't log if the model is in the ignored list
        foreach (self::$ignoredModels as $ignoredModel) {
            if ($model instanceof $ignoredModel) {
                return false;
            }
        }

        return true;
    }

    protected function isAdminContext(): bool
    {
        // Check if we're in the admin panel by checking the current panel ID
        try {
            $panel = \Filament\Facades\Filament::getCurrentPanel();
            return $panel?->getId() === 'admin';
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function logActivity(string $action, Model $model, array $changes = []): void
    {
        $modelName = class_basename($model);
        $modelIdentifier = $this->getModelIdentifier($model);

        $description = $this->buildDescription($action, $modelName, $modelIdentifier, $changes);

        $properties = [
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'action' => $action,
        ];

        if (!empty($changes)) {
            $properties['changes'] = $this->formatChanges($model, $changes);
        }

        AdminActivity::event("admin:{$modelName}:{$action}")
            ->subject($model)
            ->description($description)
            ->property($properties)
            ->log();
    }

    protected function getModelIdentifier(Model $model): string
    {
        if ($model instanceof User) {
            return $model->username;
        }

        if ($model->hasAttribute('name')) {
            return $model->getAttribute('name');
        }

        if ($model->hasAttribute('title')) {
            return $model->getAttribute('title');
        }

        return "#{$model->getKey()}";
    }

    protected function buildDescription(string $action, string $modelName, string $modelIdentifier, array $changes): string
    {
        $actor = Auth::user();
        $actorName = $actor instanceof User ? $actor->username : 'System';

        $description = "Admin user {$actorName} {$action} {$modelName} \"{$modelIdentifier}\"";

        if (!empty($changes) && $action === 'updated') {
            $changeDescriptions = [];
            foreach ($changes as $field => $newValue) {
                $changeDescriptions[] = $field;
            }

            if (!empty($changeDescriptions)) {
                $description .= ' (modified: ' . implode(', ', $changeDescriptions) . ')';
            }
        }

        return $description;
    }

    protected function formatChanges(Model $model, array $changes): array
    {
        $formatted = [];

        foreach ($changes as $field => $newValue) {
            $oldValue = $model->getOriginal($field);

            // Don't log password changes (for security)
            if (str_contains(strtolower($field), 'password')) {
                $formatted[$field] = [
                    'old' => '***',
                    'new' => '***',
                ];
                continue;
            }

            $formatted[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $formatted;
    }
}
