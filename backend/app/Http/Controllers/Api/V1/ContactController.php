<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $user = $request->user();
        $query = Contact::with(['branch:id,name', 'assignedUser:id,name', 'tagPivots:id,name,color']);

        // Branch boundary check (BOLA prevention)
        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_primary', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('tag_id')) {
            $tagId = $request->input('tag_id');
            $query->whereHas('tagPivots', function ($q) use ($tagId) {
                $q->where('tag_id', $tagId);
            });
        }

        $contacts = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone_primary' => ['required', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'array'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,vip,blocked,inactive'],
            'source' => ['nullable', 'string', 'in:call,walk_in,referral,campaign,online'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'is_vip' => ['boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:contact_tags,id'],
        ]);

        $contactData = $validated;
        unset($contactData['tag_ids']);
        $contactData['created_by'] = $request->user()->id;

        $contact = Contact::create($contactData);

        if (! empty($validated['tag_ids'])) {
            $contact->tagPivots()->sync($validated['tag_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'مخاطب با موفقیت ثبت شد.',
            'contact' => $contact->load(['branch', 'assignedUser', 'tagPivots']),
        ], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        $contact->load([
            'branch',
            'assignedUser:id,name,email,phone',
            'creator:id,name',
            'tagPivots',
            'notesList.user:id,name',
            'files',
            'calls' => fn ($q) => $q->with('user:id,name')->limit(15),
            'appointments' => fn ($q) => $q->with(['service:id,name', 'staff:id,name'])->limit(15),
            'invoices' => fn ($q) => $q->with('items')->limit(15),
            'accountingLogs' => fn ($q) => $q->limit(25),
        ]);

        return response()->json([
            'contact' => $contact,
        ]);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone_primary' => ['required', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'array'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,vip,blocked,inactive'],
            'source' => ['nullable', 'string', 'in:call,walk_in,referral,campaign,online'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'is_vip' => ['boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:contact_tags,id'],
        ]);

        $updateData = $validated;
        unset($updateData['tag_ids']);

        $contact->update($updateData);

        if (array_key_exists('tag_ids', $validated)) {
            $contact->tagPivots()->sync($validated['tag_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات مخاطب با موفقیت ویرایش شد.',
            'contact' => $contact->load(['branch', 'assignedUser', 'tagPivots']),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'مخاطب با موفقیت حذف گردید.',
        ]);
    }

    public function timeline(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        $notes = $contact->notesList()->with('user:id,name')->get()->map(function ($note) {
            return [
                'id' => 'note_'.$note->id,
                'type' => $note->type->value,
                'title' => $note->type->label(),
                'content' => $note->content,
                'author' => $note->user?->name,
                'created_at' => $note->created_at,
                'is_pinned' => $note->is_pinned,
            ];
        });

        $calls = $contact->calls()->with('user:id,name')->get()->map(function ($call) {
            $dirLabel = $call->direction->label();
            $statusLabel = $call->status->label();
            $desc = "تماس {$dirLabel} ({$statusLabel}) - مدت: {$call->duration_seconds} ثانیه";
            if ($call->disposition) {
                $desc .= " | نتیجه: {$call->disposition}";
            }
            if ($call->disposition_note) {
                $desc .= " - {$call->disposition_note}";
            }

            return [
                'id' => 'call_'.$call->id,
                'type' => 'call',
                'title' => "تماس {$dirLabel} - {$call->caller_number}",
                'content' => $desc,
                'author' => $call->user?->name,
                'created_at' => $call->started_at ?? $call->created_at,
                'is_pinned' => false,
            ];
        });

        // Timeline items sorted descending by date
        $timeline = $notes->concat($calls)->sortByDesc('created_at')->values();

        return response()->json([
            'timeline' => $timeline,
        ]);
    }

    public function addNote(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:note,task,follow_up'],
            'is_pinned' => ['boolean'],
            'due_at' => ['nullable', 'date'],
        ]);

        $note = $contact->notesList()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'note',
            'is_pinned' => $validated['is_pinned'] ?? false,
            'due_at' => $validated['due_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'یادداشت با موفقیت ثبت شد.',
            'note' => $note->load('user:id,name'),
        ], 201);
    }

    public function tags(): JsonResponse
    {
        $tags = ContactTag::orderBy('name')->get();

        return response()->json([
            'tags' => $tags,
        ]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:contact_tags,name'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $tag = ContactTag::create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#3B82F6',
        ]);

        return response()->json([
            'success' => true,
            'tag' => $tag,
        ], 201);
    }
}
