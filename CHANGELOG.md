## [1.4.0] - 2026-10-28

### Added
- Hosts can customize workspace success and error responses with a response formatter
- Hosts can use their own workspace controller, including for public invitation previews

### Fixed
- Workspace responses use the default formatter when cached configuration lacks the formatter setting

## [1.3.0] - 2026-10-23

### Added
- People can preview an invitation before signing in: the workspace, role, inviter, a masked email and whether it is still open
- Owners and admins can send a pending invitation again with a new link and expiry, and change a member's role between admin and member
- Inviting returns the invitation link when `invitations.url` is set, and the bundled invitation email can be switched on
- Invitation tokens can be stored hashed, and accepting can require a verified email
- Hosts can refuse an invitation or an acceptance, for example to enforce a seat limit, by binding `InvitationGuard`
- `MemberRoleChanged` is dispatched when a role changes, and `MemberInvited` now carries the link token and whether it was a resend

### Fixed
- Invited emails are matched regardless of case, so an address typed in different case can accept
- An address whose earlier invitation was declined or expired can be invited again instead of failing
- Inviting someone who already belongs to the workspace is refused
- The owners and admins lists find people by the role names the package stores
- Member names fall back to first and last name when the user model has no single name

## [1.2.0] - 2026-09-20

### Fixed
- Role lookups now compare the polymorphic `context_type` against the model's morph class instead of the configured class name, so a host whose subclass keeps the name already stored, or that registers a morph map, no longer sees empty workspace and member lists
- Member lookups match the user side on the name their role assignment was stored under, which a host mapping its user model had written as an alias

## [1.1.1] - 2026-06-14

### Fixed
- Member, owner, and admin lists are now found when a host app uses its own `Workspace` subclass; the lookups now match the configured model instead of the base class

## [1.1.0] - 2026-06-13

### Added
- Invitation accept and decline API endpoints, so an invited user can join (or turn down) a workspace through the same path that issues the invite
- `MemberInvited` and `MemberJoined` events are now dispatched (on invite and on accept), letting host apps react -- for example to send an invitation email

## [1.0.3] - 2026-06-11

### Fixed
- The `workspace_model` config was advertised but ignored; it is now honoured by the `HasWorkspaces` relations, role-context queries, and route-model binding, so a host app's `Workspace` subclass is used everywhere (no re-resolving)

## [1.0.2] - 2026-02-24

### Added
- Workspace features for cross-project use
- QA infrastructure (test)
- Optional workspace context switching
- `workspace:setup` command and role validation
- User role inclusion in workspace API responses

### Fixed
- Explicit route model binding for Workspace
- Auto-injection of SubstituteBindings middleware

### Changed
- Refactored roles, permissions, and workspace types to use enums

## [1.0.1] - 2025-11-16
- Change package name to whilesmart/eloquent-workspaces

## [1.0.0] - 2025-11-16
- Initial release

