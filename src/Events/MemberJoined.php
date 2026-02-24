<?php

namespace Whilesmart\Workspaces\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Workspaces\Enums\Role;
use Whilesmart\Workspaces\Models\Workspace;

class MemberJoined
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $role;

    public function __construct(
        public Workspace $workspace,
        public mixed $member,
        ?string $role = null
    ) {
        $this->role = $role ?? Role::default()->value;
    }
}
