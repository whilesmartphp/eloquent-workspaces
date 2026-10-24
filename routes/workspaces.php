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

$controller = config('workspaces.controller', WorkspaceController::class);

Route::get('/workspaces', [$controller, 'index']);
Route::post('/workspaces', [$controller, 'store']);
Route::get('/workspaces/{workspace}', [$controller, 'show']);
Route::put('/workspaces/{workspace}', [$controller, 'update']);
Route::delete('/workspaces/{workspace}', [$controller, 'destroy']);

Route::get('/workspaces/{workspace}/members', [$controller, 'members']);
Route::post('/workspaces/{workspace}/members/invite', [$controller, 'inviteMember']);
Route::patch('/workspaces/{workspace}/members/{userId}', [$controller, 'updateMember']);
Route::delete('/workspaces/{workspace}/members/{userId}', [$controller, 'removeMember']);
Route::post('/workspaces/{workspace}/leave', [$controller, 'leave']);
Route::post('/workspaces/{workspace}/switch', [$controller, 'switchTo']);

Route::get('/workspaces/{workspace}/invitations', [$controller, 'invitations']);
Route::post('/workspaces/{workspace}/invitations/{invitation}/resend', [$controller, 'resendInvitation']);
Route::delete('/workspaces/{workspace}/invitations/{invitation}', [$controller, 'cancelInvitation']);

Route::post('/workspaces/invitations/{token}/accept', [$controller, 'acceptInvitation']);
Route::post('/workspaces/invitations/{token}/decline', [$controller, 'declineInvitation']);
