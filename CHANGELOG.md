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

