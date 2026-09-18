<?php

namespace App\Policies;

use App\Models\PlacementTemplate;
use App\Models\User;

class PlacementTemplatePolicy
{
    public function update(User $user, PlacementTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }

    public function delete(User $user, PlacementTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
