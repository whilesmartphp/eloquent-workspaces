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
    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $workspaces = $user->workspaces()->get()->map(fn ($workspace) => [
            'id' => $workspace->id,
            'slug' => $workspace->slug,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'type' => $workspace->type,
            'is_personal' => $workspace->is_personal,
            'is_active' => $workspace->is_active,
            'created_at' => $workspace->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $workspaces,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|string|in:team,organization',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $workspace = $user->createWorkspace(
            $request->input('name'),
            $request->only(['description', 'type'])
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'type' => $workspace->type,
                'is_personal' => $workspace->is_personal,
            ],
        ], 201);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        if (! $this->userCanAccess($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'type' => $workspace->type,
                'is_personal' => $workspace->is_personal,
                'is_active' => $workspace->is_active,
                'settings' => $workspace->settings,
                'created_at' => $workspace->created_at,
                'owner' => $workspace->owner ? [
                    'id' => $workspace->owner->id,
                    'type' => $workspace->owner_type,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workspace->update($request->only(['name', 'description', 'settings']));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'settings' => $workspace->settings,
            ],
        ]);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        if (! $this->userIsOwner($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($workspace->is_personal) {
            return response()->json([
                'error' => 'Cannot delete personal workspace',
            ], 422);
        }

        $workspace->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workspace deleted successfully',
        ]);
    }

    public function members(Workspace $workspace): JsonResponse
    {
        if (! $this->userCanAccess($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $members = $workspace->members()
            ->with(['roleAssignments' => function ($query) use ($workspace) {
                $query->where('context_type', Workspace::class)
                    ->where('context_id', $workspace->id)
                    ->with('role');
            }])
            ->get()
            ->map(function ($member) {
                $roleAssignment = $member->roleAssignments->first();
                $role = $roleAssignment?->role->slug ?? 'workspace-member';

                return [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => str_replace('workspace-', '', $role),
                    'joined_at' => $roleAssignment?->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    public function inviteMember(Request $request, Workspace $workspace): JsonResponse
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

        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $existing = $workspace->invitations()
            ->where('email', $request->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'An invitation already exists for this email',
            ], 422);
        }

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $request->email,
            'role' => $request->role,
            'invited_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully',
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    public function invitations(Workspace $workspace): JsonResponse
    {
        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invitations = $workspace->pendingInvitations()
            ->with('invitedBy')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->invitedBy?->name,
                'expires_at' => $inv->expires_at,
                'created_at' => $inv->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    public function cancelInvitation(Workspace $workspace, WorkspaceInvitation $invitation): JsonResponse
    {
        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($invitation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        $invitation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invitation cancelled',
        ]);
    }

    public function removeMember(Workspace $workspace, string $userId): JsonResponse
    {
        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userModel = config('workspaces.user_model', 'App\\Models\\User');
        $user = $userModel::findOrFail($userId);

        if ($workspace->isOwnedBy($user)) {
            return response()->json([
                'error' => 'Cannot remove workspace owner',
            ], 422);
        }

        $user->leaveWorkspace($workspace);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);
    }

    public function leave(Workspace $workspace): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($workspace->isOwnedBy($user)) {
            return response()->json([
                'error' => 'Owner cannot leave workspace. Transfer ownership first.',
            ], 422);
        }

        $user->leaveWorkspace($workspace);

        return response()->json([
            'success' => true,
            'message' => 'Left workspace successfully',
        ]);
    }

    protected function userCanAccess(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('workspace-member', Workspace::class, $workspace->id)
            || $user->hasRole('workspace-owner', Workspace::class, $workspace->id)
            || $user->hasRole('workspace-admin', Workspace::class, $workspace->id);
    }

    protected function userCanManage(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('workspace-owner', Workspace::class, $workspace->id)
            || $user->hasRole('workspace-admin', Workspace::class, $workspace->id);
    }

    protected function userIsOwner(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('workspace-owner', Workspace::class, $workspace->id);
    }
}
