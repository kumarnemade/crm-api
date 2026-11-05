<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function view(User $user, Lead $lead): bool
    {
        return $user->isManager() || $lead->assigned_to === $user->id;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function logActivity(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function assign(User $user): bool
    {
        return $user->isManager();
    }
}
