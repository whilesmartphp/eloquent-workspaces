<?php

namespace Whilesmart\Workspaces\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Workspaces\Models\Workspace;

class MemberRoleChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public mixed $member,
        public string $from,
        public string $to,
    ) {}
}
