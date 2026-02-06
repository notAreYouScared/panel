# Panel Data Classes - Fractal Compatibility Layer

This directory contains Laravel Data classes that provide backward compatibility with Fractal transformers while enabling modern API documentation with Scramble.

## Overview

The `PanelData` base class extends Laravel Data and adds a compatibility layer that can output data in Fractal's format when needed, or as clean data objects for new API versions.

## Key Features

1. **Fractal Compatibility**: When `$isFractal = true`, outputs match Fractal's structure
2. **Clean Data Objects**: When `$isFractal = false`, outputs are standard Laravel Data arrays
3. **Type Safety**: Full TypeScript schema generation support via Laravel Data
4. **Scramble Support**: Automatic OpenAPI documentation generation

## Usage

### Creating a Data Class

```php
<?php

namespace App\Data\Application;

use App\Data\PanelData;
use App\Models\YourModel;

class YourModelData extends PanelData
{
    public function __construct(
        public int $id,
        public string $name,
        // ... other properties
    ) {}

    public static function fromModel(YourModel $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            // ... other properties
        );
    }

    public function getResourceName(): string
    {
        return YourModel::RESOURCE_NAME;
    }
}
```

### In Controllers

#### Using the helper methods:

```php
// Single item
public function show(Server $server): array
{
    $data = ServerData::fromModel($server);
    return $this->data($data);  // Returns Fractal-compatible format
}

// Collection
public function index(): array
{
    $servers = Server::all();
    $dataItems = $servers->map(fn($s) => ServerData::fromModel($s));
    return $this->dataCollection($dataItems);  // Returns Fractal list format
}
```

#### Or use directly:

```php
public function show(Server $server): array
{
    $data = ServerData::fromModel($server);
    $data->setFractal(true);
    return $data->toArray();
}
```

## Output Formats

### Fractal Mode (`$isFractal = true`)

**Single Item:**
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

**Collection:**
```json
{
  "object": "list",
  "data": [
    {
      "object": "server",
      "attributes": { ... }
    },
    ...
  ]
}
```

### Standard Mode (`$isFractal = false`)

**Single Item:**
```json
{
  "id": 1,
  "name": "My Server",
  ...
}
```

**Collection:**
```json
[
  {
    "id": 1,
    "name": "My Server",
    ...
  },
  ...
]
```

## Migration Path

1. **Phase 1** (Current): Use Data classes with Fractal mode for backward compatibility
2. **Phase 2**: Existing Fractal transformers continue to work alongside new Data classes
3. **Phase 3**: Create a new V2 API that uses Data classes in standard mode
4. **Phase 4**: Gradually migrate all endpoints to use Data classes

## Benefits

- **Maintainability**: Strongly-typed data objects instead of array transformers
- **Documentation**: Automatic OpenAPI schema generation via Scramble
- **Type Safety**: Full IDE support and static analysis
- **Modern API**: Clean, predictable data structures for new API versions
- **Backward Compatible**: Existing Pterodactyl 1.X clients continue to work

## Examples

See `app/Data/Application/ServerData.php` for a complete implementation example.

See `tests/Unit/Data/PanelDataTest.php` and `tests/Integration/Api/Application/ServerDataIntegrationTest.php` for usage examples and tests.
