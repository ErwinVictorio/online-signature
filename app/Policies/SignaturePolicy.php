<?php

namespace App\Policies;

use App\Models\Signature;
use App\Models\User;

class SignaturePolicy
{
    public function view(User $user, Signature $signature): bool
    {
        return $user->id === $signature->user_id;
    }

    public function update(User $user, Signature $signature): bool
    {
        return $this->view($user, $signature);
    }

    public function delete(User $user, Signature $signature): bool
    {
        return $this->view($user, $signature);
    }
}
