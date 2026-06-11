<?php

use Whilesmart\Workspaces\Enums\Permission;
use Whilesmart\Workspaces\Enums\Role;
use Whilesmart\Workspaces\Enums\WorkspaceType;

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class that will be used for workspace relationships.
    | This model should use the HasWorkspaces trait.
    |
    */
    'user_model' => env('WORKSPACES_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Workspace Model
    |--------------------------------------------------------------------------
    |
    | The workspace model class. Point this at your own subclass (extending the
    | base Workspace model) to compose extra traits/behaviour. It is honoured by
    | the HasWorkspaces relations, role-context queries, and route-model binding,
    | so the host app's model is used everywhere without re-resolving.
    |
    */
    'workspace_model' => env('WORKSPACES_MODEL', \Whilesmart\Workspaces\Models\Workspace::class),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing behavior for the workspace package.
    |
    */
    'register_routes' => env('WORKSPACES_REGISTER_ROUTES', true),
    'route_prefix' => env('WORKSPACES_ROUTE_PREFIX', 'api'),
    'route_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Personal Workspace Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic personal workspace creation behavior.
    |
    */
    'create_personal_workspace_on_registration' => env('WORKSPACES_AUTO_CREATE', true),
    'personal_workspace_name_template' => "{name}'s Workspace",

    /*
    |--------------------------------------------------------------------------
    | Invitation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workspace invitation behavior.
    |
    */
    'invitation_expiry_days' => env('WORKSPACES_INVITATION_EXPIRY', 7),

    /*
    |--------------------------------------------------------------------------
    | Role Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workspace roles. These roles are scoped to workspaces via
    | the context_type and context_id columns in the role_assignments table.
    |
    | Note: The core roles (owner, admin, member) are defined in the Role enum
    | and cannot be removed. You can add additional custom roles here.
    |
    */
    'roles' => [
        Role::OWNER->value => [
            'slug' => Role::OWNER->value,
            'name' => Role::OWNER->label(),
            'permissions' => [Permission::ALL->value],
        ],
        Role::ADMIN->value => [
            'slug' => Role::ADMIN->value,
            'name' => Role::ADMIN->label(),
            'permissions' => [
                Permission::MANAGE_MEMBERS->value,
                Permission::MANAGE_SETTINGS->value,
                Permission::MANAGE_INVITATIONS->value,
            ],
        ],
        Role::MEMBER->value => [
            'slug' => Role::MEMBER->value,
            'name' => Role::MEMBER->label(),
            'permissions' => [Permission::VIEW->value],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Types
    |--------------------------------------------------------------------------
    |
    | Available workspace types. Personal workspaces are auto-created for users.
    |
    | Note: The core types (personal, team, organization) are defined in the
    | WorkspaceType enum. You can add additional custom types here.
    |
    */
    'types' => [
        WorkspaceType::PERSONAL->value => WorkspaceType::PERSONAL->label(),
        WorkspaceType::TEAM->value => WorkspaceType::TEAM->label(),
        WorkspaceType::ORGANIZATION->value => WorkspaceType::ORGANIZATION->label(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Enable or disable soft deletes for workspaces.
    |
    */
    'soft_deletes' => true,
];
