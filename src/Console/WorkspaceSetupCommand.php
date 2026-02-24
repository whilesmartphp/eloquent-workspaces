<?php

namespace Whilesmart\Workspaces\Console;

use Exception;
use Illuminate\Console\Command;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Workspaces\Enums\Role as RoleEnum;

class WorkspaceSetupCommand extends Command
{
    protected $signature = 'workspace:setup';

    protected $description = 'Set up required workspace roles';

    public function handle(): int
    {
        $this->info('Setting up workspace roles...');

        $hasErrors = false;

        foreach (RoleEnum::cases() as $role) {
            try {
                Role::firstOrCreate(
                    ['slug' => $role->value],
                    ['name' => ucfirst($role->value), 'description' => "Workspace {$role->value}"]
                );
                $this->line("  ✓ Role: {$role->value}");
            } catch (Exception $e) {
                $hasErrors = true;
                $this->error("  ✗ Failed to create role '{$role->value}': {$e->getMessage()}");
            }
        }

        if ($hasErrors) {
            $this->error('Workspace setup completed with errors.');

            return self::FAILURE;
        }

        $this->info('Workspace setup complete.');

        return self::SUCCESS;
    }
}
