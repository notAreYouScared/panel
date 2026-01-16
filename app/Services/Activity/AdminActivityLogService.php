<?php

namespace App\Services\Activity;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Throwable;

class AdminActivityLogService
{
    protected ?AdminActivityLog $activity = null;

    public function __construct(
        protected AuthFactory $manager,
        protected ConnectionInterface $connection
    ) {}

    /**
     * Sets the action for this activity log.
     */
    public function event(string $action): self
    {
        $this->getActivity()->event = $action;

        return $this;
    }

    /**
     * Set the description for this activity.
     */
    public function description(?string $description): self
    {
        $this->getActivity()->description = $description;

        return $this;
    }

    /**
     * Sets the subject model instance (the resource being acted upon).
     *
     * @template T extends \Illuminate\Database\Eloquent\Model
     *
     * @param  T|null  $subject
     */
    public function subject(?Model $subject): self
    {
        if (!is_null($subject)) {
            $this->getActivity()->subject()->associate($subject);
        }

        return $this;
    }

    /**
     * Sets the actor model instance (the user performing the action).
     */
    public function actor(?Model $actor): self
    {
        if (!is_null($actor)) {
            $this->getActivity()->actor()->associate($actor);
        } else {
            $this->getActivity()->actor_id = null;
            $this->getActivity()->actor_type = null;
            $this->getActivity()->setRelation('actor', null);
        }

        return $this;
    }

    /**
     * Set a property value for the activity log.
     */
    public function property(string $key, mixed $value): self
    {
        $properties = $this->getActivity()->properties ?? collect();

        $this->getActivity()->properties = $properties->put($key, $value);

        return $this;
    }

    /**
     * Set multiple properties for the activity log.
     */
    public function properties(array $properties): self
    {
        foreach ($properties as $key => $value) {
            $this->property($key, $value);
        }

        return $this;
    }

    /**
     * Set metadata for the activity log.
     */
    public function metadata(array $metadata): self
    {
        $this->getActivity()->metadata = collect($metadata);

        return $this;
    }

    /**
     * Sets the request metadata from the current request.
     */
    public function withRequestMetadata(): self
    {
        $this->getActivity()->ip = Request::ip();

        $metadata = collect([
            'user_agent' => Request::userAgent(),
        ]);

        if ($this->getActivity()->metadata) {
            $metadata = $this->getActivity()->metadata->merge($metadata);
        }

        $this->getActivity()->metadata = $metadata;

        return $this;
    }

    /**
     * Saves the activity log to the database.
     */
    public function log(): AdminActivityLog
    {
        // Default to the authenticated user if no actor is set
        if (is_null($this->getActivity()->actor_id) && is_null($this->getActivity()->actor_type)) {
            $user = $this->manager->guard()->user();
            if ($user instanceof Model) {
                $this->actor($user);
            }
        }

        // Ensure IP is set
        if (empty($this->getActivity()->ip)) {
            $this->getActivity()->ip = Request::ip();
        }

        $this->getActivity()->save();

        $activity = $this->activity;
        $this->activity = null;

        return $activity;
    }

    /**
     * Execute a callback within a transaction and roll back if any exceptions occur.
     */
    public function transaction(callable $callback): mixed
    {
        try {
            return $this->connection->transaction(function () use ($callback) {
                $result = $callback($this);

                if (!$this->activity?->exists) {
                    $this->log();
                }

                return $result;
            });
        } catch (Throwable $exception) {
            $this->activity = null;

            throw $exception;
        }
    }

    /**
     * Get or create the activity log instance.
     */
    protected function getActivity(): AdminActivityLog
    {
        if ($this->activity) {
            return $this->activity;
        }

        $this->activity = new AdminActivityLog();
        $this->activity->properties = collect();
        $this->activity->metadata = collect();

        return $this->activity;
    }
}
