<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompanyController extends Controller
{
    public function show(): JsonResponse
    {
        $company = CompanyProfile::firstOrCreate(['id' => 1], [
            'name' => 'VoiceReach OS',
            'invoice_prefix' => 'VR-',
            'invoice_counter' => 1000,
        ]);

        return response()->json([
            'company' => $company,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('company.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'working_hours' => ['nullable', 'array'],
            'invoice_prefix' => ['nullable', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
        ]);

        $company = CompanyProfile::firstOrCreate(['id' => 1]);
        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'مشخصات شرکت با موفقیت به‌روزرسانی شد.',
            'company' => $company,
        ]);
    }
}
