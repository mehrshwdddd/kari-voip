<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Integration;
use App\Models\User;
use App\Services\Telephony\AsteriskAdapter;
use App\Services\Telephony\GenericSipAdapter;
use App\Services\Telephony\IssabelAdapter;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SetupController extends Controller
{
    /**
     * Check installation status.
     */
    public function status(): JsonResponse
    {
        $hasCompany = CompanyProfile::exists();
        $hasUser = User::exists();
        $isInstalled = $hasCompany && $hasUser;

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_installed' => $isInstalled,
                'has_company' => $hasCompany,
                'has_user' => $hasUser,
                'database_ready' => true,
                'php_version' => PHP_VERSION,
                'app_name' => 'VoiceReach OS',
            ],
        ]);
    }

    /**
     * Complete the initial system installation.
     */
    public function install(Request $request): JsonResponse
    {
        if (CompanyProfile::exists() && User::exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'سیستم قبلاً راه‌اندازی و نصب شده است.',
            ], 400);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_phone' => ['required', 'string', 'max:30'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_phone' => ['required', 'string', 'max:20'],
            'admin_password' => ['required', 'string', 'min:8'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'voip_adapter' => ['nullable', 'string', 'in:asterisk,issabel,generic_sip'],
            'voip_host' => ['nullable', 'string'],
            'voip_port' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Ensure Roles & Permissions are seeded
            (new RoleAndPermissionSeeder)->run();

            // 2. Create Company Profile
            $company = CompanyProfile::firstOrCreate([], [
                'name' => $validated['company_name'],
                'phone' => $validated['company_phone'],
                'address' => $validated['company_address'] ?? null,
                'timezone' => 'Asia/Tehran',
                'invoice_prefix' => 'VR-',
                'invoice_counter' => 1000,
            ]);

            // 3. Create Default Central Branch
            $branch = Branch::firstOrCreate(
                ['code' => $validated['branch_code'] ?? 'MAIN'],
                [
                    'name' => $validated['branch_name'] ?? 'شعبه مرکزی',
                    'phone' => $validated['company_phone'],
                    'address' => $validated['company_address'] ?? null,
                    'timezone' => 'Asia/Tehran',
                    'is_active' => true,
                ]
            );

            // 4. Create Super Admin User
            $admin = User::create([
                'branch_id' => $branch->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'phone' => $validated['admin_phone'],
                'password' => Hash::make($validated['admin_password']),
                'status' => UserStatusEnum::ACTIVE,
                'email_verified_at' => now(),
            ]);

            $admin->assignRole(RoleEnum::OWNER->value);
            $branch->update(['manager_id' => $admin->id]);

            // 5. Create VoIP integration if specified
            if (! empty($validated['voip_adapter'])) {
                $adapterClass = match ($validated['voip_adapter']) {
                    'issabel' => IssabelAdapter::class,
                    'generic_sip' => GenericSipAdapter::class,
                    default => AsteriskAdapter::class,
                };

                Integration::updateOrCreate(
                    ['type' => 'voip'],
                    [
                        'name' => 'سامانه ویپ تلفنی',
                        'adapter_class' => $adapterClass,
                        'credentials' => [
                            'host' => $validated['voip_host'] ?? '127.0.0.1',
                            'port' => $validated['voip_port'] ?? 5038,
                            'username' => 'admin',
                            'secret' => 'secret',
                        ],
                        'config' => [
                            'active_adapter' => $validated['voip_adapter'],
                        ],
                        'status' => 'active',
                    ]
                );
            }

            // 6. Audit Log
            AuditLog::create([
                'user_id' => $admin->id,
                'event_type' => 'system_install',
                'model_type' => 'App\Models\CompanyProfile',
                'model_id' => $company->id,
                'old_values' => null,
                'new_values' => ['name' => $company->name, 'admin' => $admin->email],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            // 7. Generate login token for smooth onboarding
            $token = $admin->createToken('auth-token', ['*'])->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'سامانه VoiceReach OS با موفقیت نصب و پیکربندی شد.',
                'data' => [
                    'user' => $admin,
                    'company' => $company,
                    'branch' => $branch,
                    'token' => $token,
                ],
            ], 201);
        });
    }
}
