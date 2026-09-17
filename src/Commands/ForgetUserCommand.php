<?php

namespace Arzcode\InfinitoOnboarding\Commands;

use Arzcode\InfinitoOnboarding\Support\UserData;
use Illuminate\Console\Command;

class ForgetUserCommand extends Command
{
    protected $signature = 'onboarding:forget-user
        {user : The user identifier whose seen-state and analytics events are deleted}';

    protected $description = 'Delete every onboarding completion and analytics event of a user';

    public function handle(UserData $userData): int
    {
        $deleted = $userData->forget((string) $this->argument('user'));

        $this->components->info("Deleted {$deleted['completions']} completion(s) and {$deleted['events']} event(s).");

        return self::SUCCESS;
    }
}
