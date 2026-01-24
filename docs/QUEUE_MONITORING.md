# Queue Monitoring

This implementation provides job monitoring functionality similar to the [filament-jobs-monitor](https://github.com/ultraviolettes/filament-jobs-monitor) plugin, but integrated directly into the panel without requiring the external package.

## Features

- **Real-time job monitoring**: Track running, succeeded, and failed jobs
- **Progress tracking**: Monitor job progress with visual progress bars using the existing ProgressBarColumn component
- **Queue statistics**: View job attempts, start/finish times, and execution duration
- **Exception logging**: Capture and display error messages for failed jobs
- **Automatic pruning**: Clean up old job records automatically (configurable)
- **Filament integration**: Fully integrated admin interface with filtering and search

## Usage

### Basic Job with Progress Tracking

```php
use App\Traits\QueueProgress;
use Illuminate\Contracts\Queue\ShouldQueue;

class MyJob implements ShouldQueue
{
    use QueueProgress;
    
    public function handle()
    {
        $this->setProgress(0);
        // Do work
        $this->setProgress(50);
        // Do more work
        $this->setProgress(100);
    }
}
```

### Viewing Jobs in Admin Panel

Navigate to **Advanced > Queue Jobs** in the Filament admin panel.

## Configuration

Set retention period in `config/panel.php` or `.env`:
```env
QUEUE_MONITOR_RETENTION_DAYS=7
```

Enable automatic pruning by adding to scheduler:
```php
$schedule->command('model:prune')->daily();
```

See `app/Jobs/ExampleProgressJob.php` for a complete example.
