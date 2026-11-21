<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.view')) {
            return false;
        }

        if ($user->branch_id && $invoice->branch_id && $user->branch_id !== $invoice->branch_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.manage')) {
            return false;
        }

        if ($user->branch_id && $invoice->branch_id && $user->branch_id !== $invoice->branch_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
