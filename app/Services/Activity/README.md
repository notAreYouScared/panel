# Admin Activity Logging

This service provides automatic and manual activity logging for admin panel actions.

## Automatic Logging

The `AdminActivityObserver` automatically logs create, update, and delete operations on the following models when performed in the admin panel:

- User
- Server
- Node
- Egg
- Role
- DatabaseHost
- Mount
- Webhook

### What Gets Logged

For each operation, the following information is captured:

- **Event**: e.g., `admin:User:created`, `admin:Server:updated`, `admin:Node:deleted`
- **Actor**: The admin user performing the action
- **Subject**: The model being modified
- **Description**: A human-readable description of what happened
- **Properties**: Metadata including:
  - Model class and ID
  - Action performed
  - For updates: old and new values for changed fields (passwords are redacted)
- **IP Address**: The IP address of the actor
- **Timestamp**: When the action occurred

## Manual Logging

You can also manually log admin activities using the `AdminActivity` facade:

```php
use App\Facades\AdminActivity;

// Simple log
AdminActivity::event('admin:Settings:updated')
    ->description('Updated application settings')
    ->property('setting', 'app_name')
    ->property('old_value', 'Old Name')
    ->property('new_value', 'New Name')
    ->log();

// With subject
AdminActivity::event('admin:User:password_reset')
    ->subject($user)
    ->description("Admin {$actor->username} reset password for user {$user->username}")
    ->log();

// With transaction
AdminActivity::event('admin:Server:transfer')
    ->subject($server)
    ->transaction(function ($service) use ($oldNode, $newNode) {
        $service->property('old_node', $oldNode->name);
        $service->property('new_node', $newNode->name);
        
        // Your business logic here
        $server->transferToNode($newNode);
        
        return true;
    });
```

## Permissions

To view admin activity logs, users need the `view adminActivityLog` permission. This permission can be assigned through the Roles management interface in the admin panel.

IP addresses in activity logs are only visible to users with the `view adminActivityLog` permission.

## Security Features

- **No Deletion**: Activity logs cannot be deleted through the UI
- **No Editing**: Activity logs are immutable once created
- **Password Redaction**: Password fields are automatically shown as `***` in change logs
- **Self-Exclusion**: Activity logs and related models don't generate their own activity logs

## Viewing Logs

Admin activity logs can be viewed in the admin panel under "Advanced" → "Admin Activity Log". The interface provides:

- Filtering by event type and actor
- Detailed view of each log entry including metadata
- Search functionality
- Sortable columns
- Pagination

## Database Table

The `admin_activity_logs` table stores all admin activity data with the following key fields:

- `event`: The type of action performed
- `actor_type` / `actor_id`: Polymorphic relation to the user who performed the action
- `subject_type` / `subject_id`: Polymorphic relation to the affected model
- `properties`: JSON field containing metadata and change details
- `ip`: IP address of the actor
- `timestamp`: When the action occurred
