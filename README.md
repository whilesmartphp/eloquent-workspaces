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

The `{workspace}` parameter is resolved by slug.

### Workspaces
* `GET /api/workspaces` - List the authenticated user's workspaces
* `POST /api/workspaces` - Create a workspace
* `GET /api/workspaces/{workspace}` - Get a workspace
* `PUT /api/workspaces/{workspace}` - Update a workspace
* `DELETE /api/workspaces/{workspace}` - Delete a workspace
* `POST /api/workspaces/{workspace}/switch` - Switch to a workspace

### Members
* `GET /api/workspaces/{workspace}/members` - Get workspace members
* `DELETE /api/workspaces/{workspace}/members/{userId}` - Remove a member from a workspace
* `POST /api/workspaces/{workspace}/leave` - Leave a workspace

### Invitations
* `POST /api/workspaces/{workspace}/members/invite` - Invite a member to a workspace
* `GET /api/workspaces/{workspace}/invitations` - List pending invitations
* `DELETE /api/workspaces/{workspace}/invitations/{invitation}` - Cancel a pending invitation
* `POST /api/workspaces/invitations/{token}/accept` - Accept an invitation
* `POST /api/workspaces/invitations/{token}/decline` - Decline an invitation

Accept and decline are keyed on the invitation token (the value carried in the invite). The authenticated user's email must match the address the invitation was sent to.

## Events

The package dispatches the following events, which a host application can listen for:

* `MemberInvited` - an invitation was created (carries the workspace and the invitation). Listen for this to deliver the invitation email.
* `MemberJoined` - an invited user accepted and joined (carries the workspace, the member, and the role).
* `WorkspaceSwitched` - a user switched their current workspace (carries the new workspace, the user, and the previous workspace).

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
