<?php

declare(strict_types=1);

namespace Rinvex\Fort\Console\Commands;

use Illuminate\Console\Command;
use Rinvex\Fort\Models\Ability;

class UserRevokeAbilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fort:user:revokeability
                            {user? : The user identifier}
                            {ability? : The ability identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke a ability from a user.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $userField = $this->argument('user') ?: $this->ask(trans('rinvex.fort::artisan.user.identifier'));

        if ((int) $userField) {
            $user = User::find($userField);
        } elseif (filter_var($userField, FILTER_VALIDATE_EMAIL)) {
            $user = User::where(['email' => $userField])->first();
        } else {
            $user = User::where(['username' => $userField])->first();
        }

        if (! $user) {
            return $this->error(trans('rinvex.fort::artisan.user.invalid', ['field' => $userField]));
        }

        $abilityField = $this->argument('ability') ?: $this->anticipate(trans('rinvex.fort::artisan.user.ability'), Ability::all()->pluck('slug', 'id')->toArray());

        if ((int) $abilityField) {
            $ability = Ability::find($abilityField);
        } else {
            $ability = Ability::where(['slug' => $abilityField])->first();
        }

        if (! $ability) {
            return $this->error(trans('rinvex.fort::artisan.ability.invalid', ['field' => $abilityField]));
        }

        // Revoke user ability to..
        $user->revokeAbilities($ability);

        $this->info(trans('rinvex.fort::artisan.user.abilityrevoked', ['user' => $user->id, 'ability' => $ability->id]));
    }
}
