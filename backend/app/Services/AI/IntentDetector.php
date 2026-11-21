<?php

namespace App\Services\AI;

final class IntentDetector
{
    /**
     * Detect caller intent and extract entities from a Persian utterance or DTMF input.
     *
     * @return array{intent: string, label: string, confidence: float, entities: array<string, mixed>, suggested_reply: string}
     */
    public function detect(string $utterance, ?string $dtmf = null): array
    {
        $text = trim($utterance);

        // 1. Direct DTMF shortcut mapping
        if ($dtmf !== null && $dtmf !== '') {
            return match ($dtmf) {
                '1' => [
                    'intent' => 'book_appointment',
                    'label' => 'رزرو نوبت خودکار',
                    'confidence' => 1.0,
                    'entities' => ['dtmf' => '1'],
                    'suggested_reply' => 'برای رزرو نوبت، اولین ساعت خالی کارشناس برای شما بررسی و ثبت می‌شود.',
                ],
                '2' => [
                    'intent' => 'billing_inquiry',
                    'label' => 'استعلام مالی و فاکتور',
                    'confidence' => 1.0,
                    'entities' => ['dtmf' => '2'],
                    'suggested_reply' => 'وضعیت حساب و آخرین فاکتور شما از طریق پیامک ارسال گردید.',
                ],
                '0' => [
                    'intent' => 'human_transfer',
                    'label' => 'انتقال به اپراتور انسانی',
                    'confidence' => 1.0,
                    'entities' => ['dtmf' => '0', 'target' => '101'],
                    'suggested_reply' => 'لطفاً شکیبا باشید، تماس شما در حال انتقال به منشی پذیرش است.',
                ],
                '9' => [
                    'intent' => 'opt_out',
                    'label' => 'لغو اشتراک تماس (DNC)',
                    'confidence' => 1.0,
                    'entities' => ['dtmf' => '9'],
                    'suggested_reply' => 'شماره شما در لیست عدم تماس ثبت گردید.',
                ],
                default => [
                    'intent' => 'dtmf_input',
                    'label' => "انتخاب کلید {$dtmf}",
                    'confidence' => 0.95,
                    'entities' => ['dtmf' => $dtmf],
                    'suggested_reply' => "کلید {$dtmf} دریافت شد.",
                ],
            };
        }

        $entities = $this->extractEntities($text);

        // 2. Persian Natural Language Intent Classification
        if ($this->matchesAny($text, ['اپراتور', 'منشی', 'وصل کن', 'انسان', 'داخلی', 'مسئول', 'صحبت کنم'])) {
            return [
                'intent' => 'human_transfer',
                'label' => 'درخواست اتصال به منشی انسانی',
                'confidence' => 0.96,
                'entities' => array_merge($entities, ['target' => '101']),
                'suggested_reply' => 'حتماً، هم‌اکنون تماس شما را به کارشناس پذیرش متصل می‌کنم.',
            ];
        }

        if ($this->matchesAny($text, ['ساعت خالی', 'وقت آزاد', 'کی وقت دارید', 'چه روزی خالی'])) {
            return [
                'intent' => 'check_availability',
                'label' => 'استعلام ساعات خالی نوبت‌دهی',
                'confidence' => 0.93,
                'entities' => $entities,
                'suggested_reply' => 'در حال بررسی تقویم کارشناس هستم. برای فردا ساعت ۱۰:۰۰ و ۱۱:۰۰ نوبت آزاد موجود است.',
            ];
        }

        if ($this->matchesAny($text, ['نوبت', 'وقت', 'رزرو', 'ویزیت', 'دکتر', 'مشاوره', 'ملاقات'])) {
            return [
                'intent' => 'book_appointment',
                'label' => 'رزرو نوبت جدید',
                'confidence' => 0.95,
                'entities' => $entities,
                'suggested_reply' => 'نوبت شما با موفقیت در تقویم رزرو شد و پیامک تایید برایتان ارسال گردید.',
            ];
        }

        if ($this->matchesAny($text, ['فاکتور', 'بدهی', 'قیمت', 'تعرفه', 'هزینه', 'پرداخت', 'حساب', 'مالی'])) {
            return [
                'intent' => 'billing_inquiry',
                'label' => 'استعلام صورت‌حساب و تعرفه‌ها',
                'confidence' => 0.92,
                'entities' => $entities,
                'suggested_reply' => 'اطلاعات صورت‌حساب و مانده حساب شما بررسی شد و جزئیات از طریق پیامک ارسال گردید.',
            ];
        }

        if ($this->matchesAny($text, ['مشکل', 'خرابی', 'شکایت', 'پشتیبانی', 'تیکت', 'قطع شده', 'خطا'])) {
            return [
                'intent' => 'support_ticket',
                'label' => 'ثبت تیکت پشتیبانی فنی',
                'confidence' => 0.94,
                'entities' => $entities,
                'suggested_reply' => 'گزارش شما به عنوان تیکت پشتیبانی با اولویت بالا در پرونده ثبت شد.',
            ];
        }

        if ($this->matchesAny($text, ['خرید', 'سفارش', 'فروش', 'همکاری', 'محصول', 'دمو'])) {
            return [
                'intent' => 'lead_inquiry',
                'label' => 'سرنخ فروش (Lead) و سفارش',
                'confidence' => 0.91,
                'entities' => $entities,
                'suggested_reply' => 'درخواست خرید شما در واحد فروش ثبت شد و کارشناسان ما با شما تماس خواهند گرفت.',
            ];
        }

        return [
            'intent' => 'general_greeting',
            'label' => 'خوش‌آمدگویی و راهنمایی عمومی',
            'confidence' => 0.85,
            'entities' => $entities,
            'suggested_reply' => 'سلام، به منشی هوشمند خوش آمدید. برای رزرو نوبت، استعلام حساب یا اتصال به اپراتور، درخواست خود را بفرمایید.',
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (mb_stripos($text, $kw) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract structured Persian entities (time, date, name, service) from caller utterance.
     *
     * @return array<string, mixed>
     */
    private function extractEntities(string $text): array
    {
        $entities = [];

        if (preg_match('/ساعت\s*([0-9۰-۹]{1,2}(?::[0-9۰-۹]{2})?)/u', $text, $timeMatch)) {
            $entities['time_hint'] = $this->toEnglishDigits($timeMatch[1]);
        }

        if (mb_stripos($text, 'فردا') !== false) {
            $entities['date_hint'] = now()->addDay()->toDateString();
        } elseif (mb_stripos($text, 'پس‌فردا') !== false || mb_stripos($text, 'پس فردا') !== false) {
            $entities['date_hint'] = now()->addDays(2)->toDateString();
        } elseif (mb_stripos($text, 'امروز') !== false) {
            $entities['date_hint'] = now()->toDateString();
        }

        if (preg_match('/(?:من|نامم|اسمم)\s+([آ-ی]+\s+[آ-ی]+)/u', $text, $nameMatch)) {
            $entities['customer_name'] = trim($nameMatch[1]);
        }

        return $entities;
    }

    private function toEnglishDigits(string $input): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($persian, $english, $input);
    }
}
