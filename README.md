# Eloquent Workspaces

A comprehensive Laravel package for managing workspaces, invitations, and member roles.

## Quick Start

```bash
composer require whilesmart/eloquent-workspaces
php artisan migrate
```

That's it! The package will auto-register routes and work out of the box.

## Environment Variables

All package configuration is environment-driven. Add these variables to your `.env` file:

### Core Settings
```bash
# The user model class that will be used for workspace relationships.
WORKSPACES_USER_MODEL=App\\Models\\User

# The workspace model class. Point this at your own subclass (extending the
# base Workspace model) to compose extra traits/behaviour; it is honoured by
# the HasWorkspaces relations, role-context queries, and route-model binding.
WORKSPACES_MODEL=App\\Models\\Workspace

# Enable or disable route registration (default: true)
WORKSPACES_REGISTER_ROUTES=true

# Route prefix for all workspace endpoints (default: api)
WORKSPACES_ROUTE_PREFIX=api
```

### Custom workspace model

```php
// config/workspaces.php
'workspace_model' => App\Models\Workspace::class,
```

```php
use Whilesmart\Workspaces\Models\Workspace as BaseWorkspace;

class Workspace extends BaseWorkspace
{
    // compose your own capabilities (HasInvoices, HasBrands, Configurable, …)
}
```

With this set, `$user->workspaces()`, `currentOrDefaultWorkspace()`, route-bound
`{workspace}` params, and role checks all return / use your subclass — no
re-resolving in the host app.

### Workspace Settings
```bash
# Automatically create a personal workspace for a user on registration (default: true)
WORKSPACES_AUTO_CREATE=true
```

### Invitation Settings
```bash
# Number of days an invitation is valid (default: 7)
WORKSPACES_INVITATION_EXPIRY=7
```

## Advanced Configuration

For more advanced configuration, you can publish the configuration file:

```bash
php artisan vendor:publish --tag="workspaces-config"
```

This will create a `config/workspaces.php` file in your application.

### `route_middleware`

You can specify middleware for the workspace routes.

```php
'route_middleware' => ['auth:sanctum'],
```

### `personal_workspace_name_template`

This template is used to name the personal workspace created for a new user.
Available variables: `{first_name}`, `{last_name}`, `{full_name}`

```php
'personal_workspace_name_template' => "{first_name}'s Workspace",
```

### `roles`

Define the roles available in a workspace.

```php
'roles' => [
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => 'member',
],
```

## Available Endpoints


* `GET /api/workspaces/{workspaceId}` - Get a workspace
* `PUT /api/workspaces/{workspaceId}` - Update a workspace
* `GET /api/workspaces/{workspaceId}/members` - Get workspace members
* `POST /api/workspaces/{workspaceId}/members/invite` - Invite a member to a workspace
* `DELETE /api/workspaces/{workspaceId}/members/{userId}` - Remove a member from a workspace

## Events

This package does not currently dispatch any custom events.

## Publishing Assets

You can publish the package's assets using the following commands:

```bash
# Publish only configuration
php artisan vendor:publish --tag="workspaces-config"

# Publish only migrations
php artisan vendor:publish --tag="workspaces-migrations"

# Publish everything
php artisan vendor:publish --provider="Whilesmart\\Workspaces\\WorkspacesServiceProvider"
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
