# Server Configuration Import/Export API

## Overview
API endpoints for importing and exporting server configurations in YAML format.

## Endpoints

### Export Server Configuration
Export an existing server's configuration to YAML.

```
GET /api/application/servers/{id}/config/export
```

**Query Parameters:**
- `include_description` (boolean, default: true) - Include server description
- `include_allocations` (boolean, default: true) - Include IP addresses and ports
- `include_variable_values` (boolean, default: true) - Include environment variables

**Response:** YAML file download

**Example:**
```bash
curl -X GET "https://panel.example.com/api/application/servers/1/config/export?include_description=true&include_allocations=true&include_variable_values=true" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json" \
  -o server-config.yaml
```

---

### Import Configuration to Existing Server
Import configuration from YAML file to update an existing server.

```
POST /api/application/servers/{id}/config/import
```

**Body Parameters:**
- `file` (file, required) - YAML configuration file

**Response:** Updated server object (JSON)

**Example:**
```bash
curl -X POST "https://panel.example.com/api/application/servers/1/config/import" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json" \
  -F "file=@server-config.yaml"
```

---

### Create Server from Configuration
Create a new server from a YAML configuration file.

```
POST /api/application/servers/config/create
```

**Body Parameters:**
- `file` (file, required) - YAML configuration file

**Response:** New server object (JSON)

**Example:**
```bash
curl -X POST "https://panel.example.com/api/application/servers/config/create" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json" \
  -F "file=@server-config.yaml"
```

---

## YAML Configuration Format

```yaml
version: 1.0
name: "Server Name"
description: "Optional description"

egg:
  uuid: "egg-uuid-here"
  name: "Egg Name"

settings:
  startup: "startup command"
  image: "docker image"
  skip_scripts: false

limits:
  memory: 2048
  swap: 0
  disk: 10240
  io: 500
  cpu: 200
  threads: null
  oom_killer: false

feature_limits:
  databases: 2
  allocations: 3
  backups: 5

allocations:  # Optional
  - ip: "192.168.1.100"
    port: 25565
    is_primary: true
  - ip: "192.168.1.100"
    port: 25566
    is_primary: false

variables:  # Optional
  - env_variable: "VAR_NAME"
    value: "var_value"
```

## Error Responses

### Egg Not Found
```json
{
  "errors": [
    {
      "code": "InvalidFileUploadException",
      "status": "400",
      "detail": "Egg with UUID 'abc-123' (name: Minecraft) does not exist in the system"
    }
  ]
}
```

### Invalid YAML
```json
{
  "errors": [
    {
      "code": "InvalidFileUploadException",
      "status": "400",
      "detail": "Could not parse YAML file: syntax error"
    }
  ]
}
```

## UI Integration

### Export (EditServer Page)
Navigate to: Admin Panel → Servers → Edit Server → Actions Tab → Export Config

### Import (ListServers Page)
Navigate to: Admin Panel → Servers → Import Config Button (top right)
- Select "Create New Server" to create from config
- Select "Update Existing Server" to update a server

## Notes

- The egg UUID in the configuration must exist in the target system
- Allocations will be created if they don't exist
- If an allocation port is in use, the next available port (+1) will be used
- Variable values are only imported if the variable exists in the target egg
- The authenticated user becomes the owner when creating servers via API
