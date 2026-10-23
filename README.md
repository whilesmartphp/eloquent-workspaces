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

# Link people open to accept; {token} is replaced (returned on invite and used in the email)
WORKSPACES_INVITATION_URL=https://app.example.com/invitations/{token}

# Email each invitation with the bundled template (default: false)
WORKSPACES_INVITATION_MAIL=true

# Store only a SHA-256 hash of each token (default: false). Turning this on
# invalidates links already sent as plain tokens.
WORKSPACES_HASH_INVITATION_TOKENS=true

# Only people with a verified email may accept (default: false)
WORKSPACES_INVITATION_REQUIRE_VERIFIED=true
```

Invited addresses are stored in lowercase and matched regardless of case.

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
* `GET /api/workspaces/{workspace}/members` - Get workspace members with their role
* `PATCH /api/workspaces/{workspace}/members/{userId}` - Change a member's role to `admin` or `member` (the owner's role cannot change)
* `DELETE /api/workspaces/{workspace}/members/{userId}` - Remove a member from a workspace
* `POST /api/workspaces/{workspace}/leave` - Leave a workspace

### Invitations
* `POST /api/workspaces/{workspace}/members/invite` - Invite a member to a workspace
* `GET /api/workspaces/{workspace}/invitations` - List pending invitations
* `POST /api/workspaces/{workspace}/invitations/{invitation}/resend` - Send a pending invitation again with a new link and expiry
* `DELETE /api/workspaces/{workspace}/invitations/{invitation}` - Cancel a pending invitation
* `GET /api/workspaces/invitations/{token}` - Preview an invitation before signing in (workspace, role, inviter, masked email, status). Uses `public_route_middleware`.
* `POST /api/workspaces/invitations/{token}/accept` - Accept an invitation
* `POST /api/workspaces/invitations/{token}/decline` - Decline an invitation

Accept and decline are keyed on the invitation token (the value carried in the invite). The authenticated user's email must match the address the invitation was sent to.

Inviting an address that already belongs to the workspace is refused, and an address whose earlier invitation was declined or expired can be invited again.

### Refusing invitations

Bind `Whilesmart\Workspaces\Contracts\InvitationGuard` to refuse an invitation or an acceptance, for example when a plan has no seats left. Throw an `HttpException` from `beforeInvite()` or `beforeAccept()`; both run inside a database transaction, so locks you take hold until the invitation is saved.

```php
$this->app->bind(InvitationGuard::class, SeatLimitGuard::class);
```

## Events

The package dispatches the following events, which a host application can listen for:

* `MemberInvited` - an invitation was created or sent again (carries the workspace, the invitation, the plain `token`, and `resent`). `url()` returns the link. Listen for this to deliver your own invitation email instead of the bundled one.
* `MemberRoleChanged` - a member's role changed (carries the workspace, the member, `from` and `to`).
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
