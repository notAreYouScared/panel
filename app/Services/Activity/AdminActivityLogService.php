<?php

namespace App\Services\Activity;

use App\Models\AdminActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Throwable;
use Webmozart\Assert\Assert;

class AdminActivityLogService
{
    protected ?AdminActivityLog $activity = null;

    public function __construct(
        protected AuthFactory $manager,
        protected ConnectionInterface $connection
    ) {}

    /**
     * Sets the activity logger as having been caused by an anonymous
     * user type.
     */
    public function anonymous(): self
    {
        $this->getActivity()->actor_id = null;
        $this->getActivity()->actor_type = null;
        $this->getActivity()->setRelation('actor', null);

        return $this;
    }

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
     * Sets the subject model instance.
     */
    public function subject(?Model $subject): self
    {
        if ($subject) {
            $this->getActivity()->subject()->associate($subject);
        }

        return $this;
    }

    /**
     * Sets the actor model instance.
     */
    public function actor(Model $actor): self
    {
        $this->getActivity()->actor()->associate($actor);

        return $this;
    }

    /**
     * Sets a custom property on the activity log instance.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     */
    public function property($key, $value = null): self
    {
        $properties = $this->getActivity()->properties;
        $this->activity->properties = is_array($key)
            ? $properties->merge($key)
            : $properties->put($key, $value);

        return $this;
    }

    /**
     * Attaches the instance request metadata to the activity log event.
     */
    public function withRequestMetadata(): self
    {
        return $this->property([
            'ip' => Request::getClientIp(),
            'useragent' => Request::userAgent(),
        ]);
    }

    /**
     * Logs an activity log entry with the set values and then returns the
     * model instance to the caller. If there is an exception encountered while
     * performing this action it will be logged to the disk but will not interrupt
     * the code flow.
     */
    public function log(?string $description = null): AdminActivityLog
    {
        $activity = $this->getActivity();

        if (!is_null($description)) {
            $activity->description = $description;
        }

        try {
            return $this->save();
        } catch (Throwable $exception) {
            if (config('app.env') !== 'production') {
                throw $exception;
            }

            logger()->error($exception);
        }

        return $activity;
    }

    /**
     * Returns a cloned instance of the service allowing for the creation of a base
     * activity log with the ability to change values on the fly without impact.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Executes the provided callback within the scope of a database transaction
     * and will only save the activity log entry if everything else successfully
     * settles.
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($callback) {
            $response = $callback($this);

            $this->save();

            return $response;
        });
    }

    /**
     * Resets the instance and clears out the log.
     */
    public function reset(): void
    {
        $this->activity = null;
    }

    /**
     * Returns the current activity log instance.
     */
    protected function getActivity(): AdminActivityLog
    {
        if ($this->activity) {
            return $this->activity;
        }

        $this->activity = new AdminActivityLog([
            'ip' => Request::ip(),
            'properties' => Collection::make([]),
        ]);

        if ($user = $this->manager->guard()->user()) {
            $this->actor($user);
        }

        return $this->activity;
    }

    /**
     * Saves the activity log instance.
     *
     * @throws Throwable
     */
    protected function save(): AdminActivityLog
    {
        Assert::notNull($this->activity);

        $response = $this->connection->transaction(function () {
            $this->activity->save();

            return $this->activity;
        });

        $this->activity = null;

        return $response;
    }
}
