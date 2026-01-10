<?php

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
    | The workspace model class. Override this if you want to use a custom
    | model that extends the base Workspace model.
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
    */
    'roles' => [
        'owner' => [
            'slug' => 'owner',
            'name' => 'Owner',
            'permissions' => ['*'],
        ],
        'admin' => [
            'slug' => 'admin',
            'name' => 'Administrator',
            'permissions' => ['manage_members', 'manage_settings', 'manage_invitations'],
        ],
        'member' => [
            'slug' => 'member',
            'name' => 'Member',
            'permissions' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Types
    |--------------------------------------------------------------------------
    |
    | Available workspace types. Personal workspaces are auto-created for users.
    |
    */
    'types' => [
        'personal' => 'Personal',
        'team' => 'Team',
        'organization' => 'Organization',
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
