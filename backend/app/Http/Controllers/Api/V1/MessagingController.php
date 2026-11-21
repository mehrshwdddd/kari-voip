<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\SmsProvider;
use App\Services\Messaging\MessageTemplateEngine;
use App\Services\Messaging\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessagingController extends Controller
{
    public function __construct(
        private readonly SmsManager $smsManager,
        private readonly MessageTemplateEngine $templateEngine
    ) {}

    public function templates(Request $request): JsonResponse
    {
        $query = MessageTemplate::query();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return response()->json([
            'templates' => $query->orderBy('id')->get(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'in:sms,whatsapp,email,push'],
            'category' => ['nullable', 'string', 'in:appointment,invoice,campaign,custom'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $variables = $this->templateEngine->extractVariables($validated['body']);

        $template = MessageTemplate::create([
            'name' => $validated['name'],
            'channel' => $validated['channel'] ?? 'sms',
            'category' => $validated['category'] ?? 'custom',
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'variables' => $variables,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'قالب پیامک با موفقیت ذخیره شد.',
            'template' => $template,
        ], 201);
    }

    public function updateTemplate(Request $request, MessageTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:appointment,invoice,campaign,custom'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['variables'] = $this->templateEngine->extractVariables($validated['body']);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'قالب پیامک ویرایش شد.',
            'template' => $template,
        ]);
    }

    public function destroyTemplate(MessageTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'قالب پیامک حذف شد.',
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_address' => ['required', 'string', 'max:30'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'template_id' => ['nullable', 'exists:message_templates,id'],
            'body' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $contactId = $validated['contact_id'] ?? null;
        if (! $contactId) {
            $contact = Contact::where('phone_primary', $validated['to_address'])->first();
            $contactId = $contact?->id;
        }

        if (! empty($validated['template_id'])) {
            $template = MessageTemplate::findOrFail($validated['template_id']);
            $log = $this->smsManager->sendFromTemplate(
                $template,
                $validated['to_address'],
                $validated['variables'] ?? [],
                $contactId
            );
        } else {
            $body = (string) ($validated['body'] ?? '');
            if (! empty($validated['variables'])) {
                $body = $this->templateEngine->render($body, $validated['variables']);
            }
            $log = $this->smsManager->sendSms(
                $validated['to_address'],
                $body,
                $contactId
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'پیامک با موفقیت ارسال و در لاگ پیام‌ها ثبت گردید.',
            'message_log' => $log->load(['contact:id,first_name,last_name', 'template:id,name']),
        ], 201);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = MessageLog::with([
            'contact:id,first_name,last_name,phone_primary',
            'template:id,name,category',
        ]);

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('to_address', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate($request->integer('per_page', 30));

        return response()->json($logs);
    }

    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => SmsProvider::orderBy('priority')->get(),
        ]);
    }
}
