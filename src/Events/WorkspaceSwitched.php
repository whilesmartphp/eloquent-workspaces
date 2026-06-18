<?php

namespace Whilesmart\Workspaces\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Workspaces\Models\Workspace;

class WorkspaceSwitched
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public mixed $switcher,
        public ?Workspace $previousWorkspace = null
    ) {
    }
}
