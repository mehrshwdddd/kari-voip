<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if (! $user->can('campaigns.view')) {
            return false;
        }

        if ($user->branch_id && $campaign->branch_id && $user->branch_id !== $campaign->branch_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.manage');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if (! $user->can('campaigns.manage')) {
            return false;
        }

        if ($user->branch_id && $campaign->branch_id && $user->branch_id !== $campaign->branch_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
