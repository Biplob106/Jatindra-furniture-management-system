<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * Day one command. Creates the owner account the shop logs in with.
 *
 * Nothing else in the app can create the first user: registration is not a
 * self-service flow here, and every other account is created by the owner.
 */
class CreateOwner extends Command
{
    protected $signature = 'owner:create
                            {--name= : Owner name}
                            {--phone= : Mobile number, this is the login}
                            {--password= : Password}
                            {--email= : Optional email address}
                            {--force : Create another owner even though one exists}';

    protected $description = 'Create the owner account';

    public function handle(CreateUser $createUser): int
    {
        if (! $this->option('force') && $this->ownerExists()) {
            $this->components->error('An owner account already exists. Pass --force to create another.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text('Owner name', required: true);
        $phone = $this->option('phone') ?: text('Mobile number (this is the login)', required: true);
        $email = $this->option('email');
        $password = $this->option('password') ?: promptPassword('Password', required: true);

        $validator = Validator::make(
            compact('name', 'phone', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:150'],
                'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
                'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $owner = $createUser->handle(
            name: $name,
            phone: $phone,
            password: $password,
            role: Role::Owner,
            email: $email ?: null,
        );

        $this->components->info("Owner account created. Log in with {$owner->phone}.");

        return self::SUCCESS;
    }

    private function ownerExists(): bool
    {
        return User::role(Role::Owner->value)->exists();
    }
}
