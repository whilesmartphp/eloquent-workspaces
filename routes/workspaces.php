<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Workspaces\Http\Controllers\WorkspaceController;

/*
|--------------------------------------------------------------------------
| Workspaces API Routes
|--------------------------------------------------------------------------
|
| API routes for workspace management. These routes are automatically
| registered by the WorkspacesServiceProvider when enabled in config.
|
*/

Route::get('/workspaces', [WorkspaceController::class, 'index']);
Route::post('/workspaces', [WorkspaceController::class, 'store']);
Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update']);
Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy']);

Route::get('/workspaces/{workspace}/members', [WorkspaceController::class, 'members']);
Route::post('/workspaces/{workspace}/members/invite', [WorkspaceController::class, 'inviteMember']);
Route::delete('/workspaces/{workspace}/members/{userId}', [WorkspaceController::class, 'removeMember']);
Route::post('/workspaces/{workspace}/leave', [WorkspaceController::class, 'leave']);
Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switchTo']);

Route::get('/workspaces/{workspace}/invitations', [WorkspaceController::class, 'invitations']);
Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceController::class, 'cancelInvitation']);

Route::post('/workspaces/invitations/{token}/accept', [WorkspaceController::class, 'acceptInvitation']);
Route::post('/workspaces/invitations/{token}/decline', [WorkspaceController::class, 'declineInvitation']);
