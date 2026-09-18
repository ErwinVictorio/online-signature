<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSigningUser extends Command
{
    protected $signature = 'signing:create-user';

    protected $description = 'Create an account for the private PDF signing application';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Name'), 'email' => $this->ask('Email'), 'password' => $this->secret('Password (at least 12 characters)'), 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(collect($data)->except('password_confirmation')->all());
        $this->info('Account created. You can now sign in.');

        return self::SUCCESS;
    }
}
