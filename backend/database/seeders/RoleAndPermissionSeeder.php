<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'company.view',
            'company.update',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.manage',
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'contacts.export',
            'contacts.import',
            'calls.view',
            'calls.make',
            'calls.recording.listen',
            'campaigns.view',
            'campaigns.manage',
            'appointments.view',
            'appointments.manage',
            'invoices.view',
            'invoices.manage',
            'sms.send',
            'sms.templates',
            'reports.view',
            'settings.manage',
            'developer.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'sanctum');
        }

        // 1. Owner
        $ownerRole = Role::findOrCreate(RoleEnum::OWNER->value, 'sanctum');
        $ownerRole->syncPermissions(Permission::where('guard_name', 'sanctum')->get());

        // 2. Branch Manager
        $branchManagerRole = Role::findOrCreate(RoleEnum::BRANCH_MANAGER->value, 'sanctum');
        $branchManagerRole->syncPermissions([
            'branches.view',
            'users.view',
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.export',
            'calls.view',
            'calls.make',
            'calls.recording.listen',
            'appointments.view',
            'appointments.manage',
            'invoices.view',
            'reports.view',
        ]);

        // 3. Receptionist
        $receptionistRole = Role::findOrCreate(RoleEnum::RECEPTIONIST->value, 'sanctum');
        $receptionistRole->syncPermissions([
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'calls.view',
            'calls.make',
            'appointments.view',
            'appointments.manage',
            'sms.send',
        ]);

        // 4. Call Agent
        $callAgentRole = Role::findOrCreate(RoleEnum::CALL_AGENT->value, 'sanctum');
        $callAgentRole->syncPermissions([
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'calls.view',
            'calls.make',
        ]);

        // 5. Sales Agent
        $salesAgentRole = Role::findOrCreate(RoleEnum::SALES_AGENT->value, 'sanctum');
        $salesAgentRole->syncPermissions([
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'invoices.view',
            'invoices.manage',
            'appointments.view',
            'appointments.manage',
            'calls.make',
        ]);

        // 6. Accountant
        $accountantRole = Role::findOrCreate(RoleEnum::ACCOUNTANT->value, 'sanctum');
        $accountantRole->syncPermissions([
            'invoices.view',
            'invoices.manage',
            'reports.view',
            'contacts.view',
        ]);

        // 7. Marketing
        $marketingRole = Role::findOrCreate(RoleEnum::MARKETING->value, 'sanctum');
        $marketingRole->syncPermissions([
            'campaigns.view',
            'campaigns.manage',
            'sms.send',
            'sms.templates',
            'contacts.view',
            'reports.view',
        ]);

        // 8. Developer
        $developerRole = Role::findOrCreate(RoleEnum::DEVELOPER->value, 'sanctum');
        $developerRole->syncPermissions([
            'company.view',
            'settings.manage',
            'reports.view',
        ]);
    }
}
