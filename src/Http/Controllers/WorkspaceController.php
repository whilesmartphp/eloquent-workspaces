<?php

namespace Whilesmart\Workspaces\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

class WorkspaceController extends Controller
{
    public function show(string $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);

        if (! auth()->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        // Check if user has access to this workspace
        if (! auth()->user()->hasRole('workspace-member', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-owner', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-admin', Workspace::class, $workspace->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'type' => $workspace->type,
                'is_active' => $workspace->is_active,
                'created_at' => $workspace->created_at,
                'ownerId' => $workspace->owners->first()?->id,
            ],
        ]);
    }

    public function update(Request $request, string $workspaceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = Workspace::findOrFail($workspaceId);

        // Check if user can edit workspace
        if (! auth()->user()->hasRole('workspace-owner', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-admin', Workspace::class, $workspace->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workspace->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'ownerId' => $workspace->owners->first()?->id,
            ],
        ]);
    }

    public function members(string $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Check if user has access to this workspace
        if (! auth()->user()->hasRole('workspace-member', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-owner', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-admin', Workspace::class, $workspace->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $members = $workspace->members()
            ->with(['roleAssignments' => function ($query) use ($workspaceId) {
                $query->where('context_type', Workspace::class)
                    ->where('context_id', $workspaceId)
                    ->with('role');
            }])
            ->get()
            ->map(function ($member) {
                $roleAssignment = $member->roleAssignments->first();
                $role = $roleAssignment?->role->slug ?? 'workspace-member';

                return [
                    'user_id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'role' => $role,
                    'joined_at' => $roleAssignment?->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    public function inviteMember(Request $request, string $workspaceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'role' => 'required|in:member,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workspace = Workspace::findOrFail($workspaceId);

        // Check if user can invite members
        if (! auth()->user()->hasRole('workspace-owner', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-admin', Workspace::class, $workspace->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $request->email,
            'role' => $request->role,
            'invited_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully',
        ]);
    }

    public function removeMember(string $workspaceId, string $userId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Check if user can manage members
        if (! auth()->user()->hasRole('workspace-owner', Workspace::class, $workspace->id) &&
            ! auth()->user()->hasRole('workspace-admin', Workspace::class, $workspace->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userModel = config('workspaces.user_model', 'App\\Models\\User');
        $user = $userModel::findOrFail($userId);

        // Remove all roles for this user in this workspace
        $user->roleAssignments()
            ->where('context_type', Workspace::class)
            ->where('context_id', $workspace->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ], 204);
    }
}
