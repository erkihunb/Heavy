<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateStaffUser;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Opens the first Admin account on a new installation (every later staff
 * account is opened in Admin → Users). Nobody types a password here: the
 * link to choose one is printed, because e-mail is usually not set up yet.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email : The Admin\'s e-mail address} {--name= : Full name}';

    protected $description = 'Open an Admin account and print the link to choose its password';

    public function handle(CreateStaffUser $createStaffUser): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: 'Administrator'));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $createStaffUser->handle($name, $email, Role::Admin, null, notify: false);

        $this->info("Admin account opened for {$email}.");
        $this->line('Choose the password within '.config('auth.passwords.users.expire').' minutes at:');
        $this->line(route('password.reset', ['token' => $this->token($user), 'email' => $email]));
        $this->line('Then sign in and turn on two-factor authentication.');

        return self::SUCCESS;
    }

    private function token(User $user): string
    {
        return Password::broker()->createToken($user);
    }
}
