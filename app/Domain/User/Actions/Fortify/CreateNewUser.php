<?php

namespace App\Domain\User\Actions\Fortify;

use App\Domain\User\Actions\RegistrationIsOpenAction;
use App\Domain\User\Concerns\PasswordValidationRules;
use App\Domain\User\Concerns\ProfileValidationRules;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort_unless(app(RegistrationIsOpenAction::class)->handle(), 403, 'Registration is closed on this installation.');

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
