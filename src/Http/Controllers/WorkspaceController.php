<?php

namespace Whilesmart\Workspaces\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Whilesmart\Workspaces\Contracts\InvitationGuard;
use Whilesmart\Workspaces\Enums\Role;
use Whilesmart\Workspaces\Enums\WorkspaceType;
use Whilesmart\Workspaces\Events\MemberInvited;
use Whilesmart\Workspaces\Events\MemberRoleChanged;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;
use Whilesmart\Workspaces\Support\Names;

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
            'role' => $this->getUserRole($workspace),
            'created_at' => $workspace->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $workspaces,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $creatableTypes = implode(',', WorkspaceType::creatableValues());

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => "nullable|string|in:{$creatableTypes}",
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
                'role' => $this->getUserRole($workspace),
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
        ], 204);
    }

    public function members(Workspace $workspace): JsonResponse
    {
        if (! $this->userCanAccess($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $members = $workspace->members()
            ->with(['roleAssignments' => function ($query) use ($workspace) {
                $query->where('context_type', $workspace->getMorphClass())
                    ->where('context_id', $workspace->id)
                    ->with('role');
            }])
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($member) {
                $held = $member->roleAssignments->pluck('role.slug')->all();
                $role = collect(Role::byPrecedence())->first(fn (Role $role) => in_array($role->value, $held, true));

                return [
                    'user_id' => $member->id,
                    'name' => Names::of($member),
                    'email' => $member->email,
                    'role' => ($role ?? Role::default())->value,
                    'joined_at' => $member->roleAssignments->min('created_at'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    public function inviteMember(Request $request, Workspace $workspace): JsonResponse
    {
        $invitableRoles = implode(',', [Role::MEMBER->value, Role::ADMIN->value]);
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'role' => "required|in:{$invitableRoles}",
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

        $email = mb_strtolower(trim((string) $request->input('email')));

        return DB::transaction(function () use ($request, $workspace, $email) {
            app(InvitationGuard::class)->beforeInvite($workspace, $email, $request->input('role'), auth()->user());

            if ($workspace->members()->whereRaw('lower(email) = ?', [$email])->exists()) {
                return response()->json(['error' => 'This email already belongs to the workspace'], 422);
            }

            $existing = $workspace->invitations()->where('email', $email)->first();
            if ($existing?->isValid()) {
                return response()->json([
                    'error' => 'An invitation already exists for this email',
                ], 422);
            }
            $existing?->delete();

            $invitation = WorkspaceInvitation::create([
                'workspace_id' => $workspace->id,
                'email' => $email,
                'role' => $request->input('role'),
                'invited_by_user_id' => auth()->id(),
            ]);

            MemberInvited::dispatch($workspace, $invitation, $invitation->plainToken);

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully',
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'expires_at' => $invitation->expires_at,
                    'url' => $invitation->url(),
                ],
            ], 201);
        });
    }

    public function resendInvitation(Workspace $workspace, WorkspaceInvitation $invitation): JsonResponse
    {
        if (! $this->userCanManage($workspace)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($invitation->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        if (! $invitation->isPending()) {
            return response()->json(['error' => 'This invitation has already been actioned'], 422);
        }

        return DB::transaction(function () use ($workspace, $invitation) {
            app(InvitationGuard::class)->beforeInvite($workspace, $invitation->email, $invitation->role, auth()->user());
            $token = $invitation->refreshToken();

            MemberInvited::dispatch($workspace, $invitation, $token, true);

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent again',
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'expires_at' => $invitation->expires_at,
                    'url' => $invitation->url(),
                ],
            ]);
        });
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
                'invited_by' => Names::of($inv->invitedBy),
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

    public function previewInvitation(string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::findByToken($token);
        if (! $invitation || ! $invitation->workspace) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        $viewer = auth()->user();
        $status = match (true) {
            $invitation->isAccepted() => 'accepted',
            $invitation->isDeclined() => 'declined',
            $invitation->isExpired() => 'expired',
            default => 'pending',
        };

        return response()->json([
            'success' => true,
            'data' => [
                'workspace' => ['name' => $invitation->workspace->name, 'slug' => $invitation->workspace->slug],
                'role' => $invitation->role,
                'invited_by' => Names::of($invitation->invitedBy),
                'email' => config('workspaces.invitations.mask_preview_email', true) ? Names::mask($invitation->email) : $invitation->email,
                'expires_at' => $invitation->expires_at,
                'status' => $status,
                'addressed_to_viewer' => $viewer ? $invitation->isFor($viewer->email) : null,
            ],
        ]);
    }

    public function acceptInvitation(string $token): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $invitation = WorkspaceInvitation::findByToken($token);
        if (! $invitation) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        if (! $invitation->isFor($user->email)) {
            return response()->json(['error' => 'This invitation was sent to a different email address'], 403);
        }

        if (config('workspaces.invitations.require_verified_email', false) && ! $user->email_verified_at) {
            return response()->json(['error' => 'Verify your email address before accepting this invitation'], 403);
        }

        if (! $invitation->isValid()) {
            $reason = $invitation->isExpired()
                ? 'This invitation has expired'
                : 'This invitation has already been actioned';

            return response()->json(['error' => $reason], 422);
        }

        $accepted = DB::transaction(function () use ($invitation, $user) {
            app(InvitationGuard::class)->beforeAccept($invitation, $user);

            return $user->acceptInvitation($invitation->fresh());
        });

        if (! $accepted) {
            return response()->json(['error' => 'Unable to accept invitation'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted',
            'data' => [
                'workspace' => [
                    'id' => $invitation->workspace->id,
                    'slug' => $invitation->workspace->slug,
                    'name' => $invitation->workspace->name,
                ],
                'role' => $invitation->role,
            ],
        ]);
    }

    public function declineInvitation(string $token): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $invitation = WorkspaceInvitation::findByToken($token);
        if (! $invitation) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        if (! $invitation->isFor($user->email)) {
            return response()->json(['error' => 'This invitation was sent to a different email address'], 403);
        }

        if (! $invitation->isPending()) {
            return response()->json(['error' => 'This invitation has already been actioned'], 422);
        }

        if (! $user->declineInvitation($invitation)) {
            return response()->json(['error' => 'Unable to decline invitation'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation declined',
        ]);
    }

    public function updateMember(Request $request, Workspace $workspace, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:'.implode(',', [Role::MEMBER->value, Role::ADMIN->value]),
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

        $userModel = config('workspaces.user_model', 'App\\Models\\User');
        $member = $userModel::find($userId);

        if (! $member || ! $member->belongsToWorkspace($workspace)) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        if ($workspace->isOwnedBy($member)) {
            return response()->json(['error' => 'The owner role cannot be changed'], 422);
        }

        $from = collect(Role::byPrecedence())
            ->first(fn (Role $role) => $member->hasRole($role->value, $workspace->getMorphClass(), $workspace->id))?->value
            ?? Role::default()->value;
        $to = $request->input('role');

        if ($from !== $to) {
            DB::transaction(function () use ($member, $workspace, $to) {
                $member->leaveWorkspace($workspace);
                $member->joinWorkspace($workspace, $to);
            });
            MemberRoleChanged::dispatch($workspace, $member, $from, $to);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated',
            'data' => ['user_id' => $member->id, 'role' => $to],
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

    /**
     * Switch to a workspace.
     *
     * This endpoint is optional - apps can handle workspace context switching
     * entirely at the application level if preferred. The actual persistence
     * of the current workspace depends on the model implementing
     * setCurrentWorkspaceId()/getCurrentWorkspaceId() methods.
     */
    public function switchTo(Workspace $workspace): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $this->userCanAccess($workspace)) {
            return response()->json(['error' => 'You do not have access to this workspace'], 403);
        }

        if (method_exists($user, 'switchWorkspace')) {
            $user->switchWorkspace($workspace);
        }

        return response()->json([
            'success' => true,
            'message' => 'Switched to workspace',
            'data' => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'type' => $workspace->type,
            ],
        ]);
    }

    protected function userCanAccess(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (Role::cases() as $role) {
            if ($role->canAccess() && $user->hasRole($role->value, $workspace->getMorphClass(), $workspace->id)) {
                return true;
            }
        }

        return false;
    }

    protected function userCanManage(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (Role::cases() as $role) {
            if ($role->canManage() && $user->hasRole($role->value, $workspace->getMorphClass(), $workspace->id)) {
                return true;
            }
        }

        return false;
    }

    protected function userIsOwner(Workspace $workspace): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(Role::OWNER->value, $workspace->getMorphClass(), $workspace->id);
    }

    protected function getUserRole(Workspace $workspace): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        foreach (Role::byPrecedence() as $role) {
            if ($user->hasRole($role->value, $workspace->getMorphClass(), $workspace->id)) {
                return $role->value;
            }
        }

        return null;
    }
}
