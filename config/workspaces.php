<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class that will be used for workspace relationships.
    |
    */
    'user_model' => env('WORKSPACES_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing behavior for the workspace package.
    |
    */
    'register_routes' => env('WORKSPACES_REGISTER_ROUTES', true),
    'route_prefix' => env('WORKSPACES_ROUTE_PREFIX', ''),
    'route_middleware' => [], // auth:sanctum

    /*
    |--------------------------------------------------------------------------
    | Default Workspace Creation
    |--------------------------------------------------------------------------
    |
    | Configure automatic workspace creation behavior.
    |
    */
    'create_personal_workspace_on_registration' => env('WORKSPACES_AUTO_CREATE', true),
    'personal_workspace_name_template' => "{first_name}'s Workspace",

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
    | Permission Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workspace permissions and roles.
    |
    */
    'roles' => [
        'owner' => 'owner',
        'admin' => 'admin',
        'member' => 'member',
    ],
];
