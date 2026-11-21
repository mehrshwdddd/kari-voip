<?php

namespace App\Services\Campaign;

use App\Enums\ContactSourceEnum;
use App\Enums\ContactStatusEnum;
use App\Models\Contact;
use App\Models\ContactList;

final class ContactListImporter
{
    /**
     * Import contacts from raw CSV/text content (each line: phone[,first_name,last_name]).
     *
     * @return array{contact_list: ContactList, valid_phones: array<int, string>, invalid_count: int, duplicate_count: int}
     */
    public function importFromCsvContent(
        string $listName,
        string $csvContent,
        ?int $userId = null,
        ?int $branchId = null
    ): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $rows = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $parts = str_getcsv($trimmed);
            $firstCol = trim((string) ($parts[0] ?? ''));

            // Skip header row if non-numeric
            if ($index === 0 && ! preg_match('/[0-9]/', $firstCol)) {
                continue;
            }

            $rows[] = [
                'phone' => $firstCol,
                'first_name' => isset($parts[1]) ? trim((string) $parts[1]) : null,
                'last_name' => isset($parts[2]) ? trim((string) $parts[2]) : null,
            ];
        }

        return $this->importRows($listName, $rows, 'csv', $userId, $branchId);
    }

    /**
     * Import contacts from an array of phone numbers or structured rows in chunks.
     *
     * @param  array<int, string|array<string, mixed>>  $rawRows
     * @return array{contact_list: ContactList, valid_phones: array<int, string>, invalid_count: int, duplicate_count: int}
     */
    public function importRows(
        string $listName,
        array $rawRows,
        string $source = 'manual',
        ?int $userId = null,
        ?int $branchId = null
    ): array {
        $totalCount = count($rawRows);
        $validPhones = [];
        $seen = [];
        $duplicateCount = 0;
        $invalidCount = 0;

        foreach (array_chunk($rawRows, 250) as $chunk) {
            foreach ($chunk as $item) {
                $rawPhone = is_array($item) ? (string) ($item['phone'] ?? '') : (string) $item;
                $firstName = is_array($item) ? ($item['first_name'] ?? null) : null;
                $lastName = is_array($item) ? ($item['last_name'] ?? null) : null;

                $normalized = DNCChecker::normalizePhone($rawPhone);

                // Valid Iranian mobile (09xxxxxxxxx) or landline (0xxxxxxxxxx)
                if (! preg_match('/^0[1-9][0-9]{8,9}$/', $normalized)) {
                    $invalidCount++;

                    continue;
                }

                if (isset($seen[$normalized])) {
                    $duplicateCount++;

                    continue;
                }

                $seen[$normalized] = true;
                $validPhones[] = $normalized;

                // Ensure Contact exists in CRM
                Contact::firstOrCreate(
                    ['phone_primary' => $normalized],
                    [
                        'branch_id' => $branchId,
                        'first_name' => ! empty($firstName) ? $firstName : 'مخاطب',
                        'last_name' => ! empty($lastName) ? $lastName : substr($normalized, -4),
                        'status' => ContactStatusEnum::ACTIVE,
                        'source' => ContactSourceEnum::CAMPAIGN,
                        'created_by' => $userId,
                    ]
                );
            }
        }

        $contactList = ContactList::create([
            'name' => $listName,
            'source' => $source,
            'total_count' => $totalCount,
            'valid_count' => count($validPhones),
            'duplicate_count' => $duplicateCount,
            'status' => 'ready',
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        return [
            'contact_list' => $contactList,
            'valid_phones' => $validPhones,
            'invalid_count' => $invalidCount,
            'duplicate_count' => $duplicateCount,
        ];
    }
}
