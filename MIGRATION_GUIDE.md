# Migration Guide: From Fractal Transformers to Panel Data Classes

This guide shows how to migrate from Fractal transformers to Panel Data classes while maintaining backward compatibility.

## Example: Server Endpoint Migration

### Before (Using Fractal Transformer)

```php
<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Models\Server;
use App\Transformers\Api\Application\ServerTransformer;

class ServerController extends ApplicationApiController
{
    /**
     * Get a single server
     */
    public function view(Server $server): array
    {
        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * List all servers
     */
    public function index(): array
    {
        $servers = Server::paginate(50);

        return $this->fractal->collection($servers)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }
}
```

### After (Using Panel Data Classes)

```php
<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Application\ServerData;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Models\Server;
use App\Services\Servers\EnvironmentService;

class ServerController extends ApplicationApiController
{
    public function __construct(
        private EnvironmentService $environmentService
    ) {
        parent::__construct();
    }

    /**
     * Get a single server
     *
     * @response 200 ServerData
     */
    public function view(Server $server): array
    {
        $environment = $this->environmentService->handle($server);
        $data = ServerData::fromModel($server, $environment);
        
        // Use helper method that automatically sets Fractal mode
        return $this->data($data);
    }

    /**
     * List all servers
     *
     * @response 200 ServerData[]
     */
    public function index(): array
    {
        $servers = Server::paginate(50);
        
        $dataItems = $servers->map(function ($server) {
            $environment = $this->environmentService->handle($server);
            return ServerData::fromModel($server, $environment);
        });
        
        // Use helper method that automatically sets Fractal mode
        return $this->dataCollection($dataItems);
    }
}
```

## Key Differences

### 1. Type Safety

**Before:**
```php
// Transformer returns untyped array
public function transform($server): array
{
    return [
        'id' => $server->id,
        'name' => $server->name,
        // Easy to make typos, no IDE support
    ];
}
```

**After:**
```php
// Data class with strong typing
public function __construct(
    public int $id,
    public string $name,
    // Full IDE autocomplete and type checking
) {}
```

### 2. Documentation

**Before:**
- Manual documentation required
- No automatic schema generation
- Scramble can't parse transformers

**After:**
- Automatic OpenAPI schema via Scramble
- TypeScript definitions via Laravel Data
- IDE autocomplete for API responses
- `@response` annotations work automatically

### 3. Nested Objects

**Before:**
```php
// Array nesting in transformer
return [
    'limits' => [
        'memory' => $server->memory,
        'swap' => $server->swap,
    ],
];
```

**After:**
```php
// Type-safe nested data objects
public function __construct(
    public ServerLimitsData $limits,
    // Nested objects are also documented
) {}
```

## Creating a New V2 API (No Fractal Compatibility)

Once you're ready to create a new API version that doesn't need Fractal compatibility:

```php
<?php

namespace App\Http\Controllers\Api\V2;

use App\Data\Application\ServerData;
use App\Http\Controllers\Controller;
use App\Models\Server;

class ServerController extends Controller
{
    /**
     * Get a single server (V2 - Clean format)
     *
     * @response 200 ServerData
     */
    public function show(Server $server): array
    {
        $data = ServerData::fromModel($server);
        
        // Don't set Fractal mode - returns clean structure
        return $data->toArray();
    }

    /**
     * Or return as JSON response
     */
    public function show(Server $server): JsonResponse
    {
        $data = ServerData::fromModel($server);
        return $data->toResponse($request);
    }
}
```

This produces clean output:
```json
{
  "id": 1,
  "external_id": null,
  "uuid": "abc123",
  "name": "My Server",
  "limits": {
    "memory": 1024,
    "swap": 512
  }
}
```

Instead of Fractal's wrapped format:
```json
{
  "object": "server",
  "attributes": {
    "id": 1,
    "name": "My Server",
    ...
  }
}
```

## Benefits Summary

1. **Backward Compatible**: Existing Pterodactyl clients work unchanged
2. **Better Documentation**: Automatic OpenAPI schemas with Scramble
3. **Type Safety**: Full IDE support and static analysis with PHPStan
4. **Maintainable**: Easier to understand and modify than transformers
5. **Future Ready**: Clean path to modern API design (V2)
6. **TypeScript**: Generate TypeScript definitions automatically

## Migration Strategy

1. **Phase 1**: Add Data classes alongside existing transformers
2. **Phase 2**: Update controllers to use Data classes (maintain Fractal compatibility)
3. **Phase 3**: Test extensively with existing clients
4. **Phase 4**: Create V2 API with clean data format
5. **Phase 5**: Deprecate Fractal transformers (long-term)

## Resources

- [Laravel Data Documentation](https://spatie.be/docs/laravel-data)
- [Scramble Documentation](https://scramble.dedoc.co/)
- `app/Data/README.md` - Implementation details
- `tests/Unit/Data/PanelDataTest.php` - Usage examples
