# Server Configuration Import/Export

This feature allows you to export a server's configuration, settings, limits, allocations, and variable values to a YAML file that can be re-imported to another panel to keep the settings and variable values the same.

## Features

### Export
- Export server settings (name, description, startup command, Docker image)
- Export resource limits (memory, swap, disk, CPU, I/O)
- Export feature limits (databases, allocations, backups)
- Export egg UUID and name for matching during import
- Optional: Export server description
- Optional: Export allocations (IP + Port)
- Optional: Export variable values

### Import
- Parse YAML file with server configuration
- Validate egg UUID exists in the system (with egg name for reference)
- Match variables and variable values from the export
- Handle allocations intelligently:
  - If allocation doesn't exist, create it
  - If allocation exists and is available, assign it to the server
  - If allocation is in use, find the next available port (+1 increment)
- Apply all configuration to the target server

## Usage

### Exporting a Server Configuration

1. Navigate to the server's edit page in the admin panel
2. Go to the "Actions" tab
3. Click the "Export Config" button
4. Choose which optional components to include:
   - ☑ Include Description
   - ☑ Include Allocations
   - ☑ Include Variable Values
5. Click "Export" to download the YAML file

The exported file will be named: `server-config-{server-name}.yaml`

### Importing a Server Configuration

1. Navigate to the target server's edit page in the admin panel
2. Go to the "Actions" tab
3. Click the "Import Config" button
4. Upload the YAML file exported from another panel
5. Click "Import" to apply the configuration

**Important Notes:**
- The egg UUID in the exported file must match an egg in the target panel
- If the egg doesn't exist, the import will fail with an error message
- Allocations will be created or reassigned as needed
- Variables will only be imported if they exist in the target egg

## File Format

The export file is in YAML format. See `docs/example-server-config-export.yaml` for a complete example.

### Structure

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

variables:  # Optional
  - env_variable: "VAR_NAME"
    value: "var_value"
```

## Error Handling

### Common Errors

1. **Egg UUID not found**
   - Error: "Egg with UUID '{uuid}' (name: {name}) does not exist in the system"
   - Solution: Install the required egg on the target panel first

2. **Invalid YAML file**
   - Error: "Could not parse YAML file: {error}"
   - Solution: Ensure the file is valid YAML format

3. **No available ports**
   - Error: "Could not find an available port for IP {ip} starting from port {port}"
   - Solution: Add more allocations to the node or use a different IP

## Technical Details

### Services

- `ServerConfigExporterService`: Handles exporting server configuration to YAML
- `ServerConfigImporterService`: Handles importing server configuration from YAML

### Actions

- `ExportServerConfigAction`: Filament action for the export UI
- `ImportServerConfigAction`: Filament action for the import UI

### Location in UI

Both actions are available in the **EditServer** page under the **Actions** tab, alongside other server management actions like suspend/unsuspend, transfer, and reinstall.

## Security Considerations

- Only users with "view server" permission can export configurations
- Only users with "update server" permission can import configurations
- Sensitive data like API keys should not be stored in environment variables if you plan to export/import configurations
- The export includes all variable values, including potentially sensitive ones

## Future Enhancements

Potential improvements for future versions:
- Export/import database configurations
- Export/import scheduled tasks
- Export/import mount configurations
- Batch import/export for multiple servers
- Import validation before applying changes
