<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Workspaces\Http\Controllers\WorkspaceController;

/*
|--------------------------------------------------------------------------
| Workspaces API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for workspace management. These routes are
| automatically registered by the WorkspacesServiceProvider.
|
*/

// Workspace Management
Route::get('/workspaces/{workspaceId}', [WorkspaceController::class, 'show']);
Route::put('/workspaces/{workspaceId}', [WorkspaceController::class, 'update']);
Route::get('/workspaces/{workspaceId}/members', [WorkspaceController::class, 'members']);
Route::post('/workspaces/{workspaceId}/members/invite', [WorkspaceController::class, 'inviteMember']);
Route::delete('/workspaces/{workspaceId}/members/{userId}', [WorkspaceController::class, 'removeMember']);
