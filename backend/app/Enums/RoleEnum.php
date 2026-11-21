<?php

namespace App\Enums;

enum RoleEnum: string
{
    case OWNER = 'owner';
    case BRANCH_MANAGER = 'branch_manager';
    case RECEPTIONIST = 'receptionist';
    case CALL_AGENT = 'call_agent';
    case SALES_AGENT = 'sales_agent';
    case ACCOUNTANT = 'accountant';
    case MARKETING = 'marketing';
    case DEVELOPER = 'developer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'مدیر ارشد (مالک)',
            self::BRANCH_MANAGER => 'مدیر شعبه',
            self::RECEPTIONIST => 'منشی / اپراتور',
            self::CALL_AGENT => 'کارشناس تماس',
            self::SALES_AGENT => 'کارشناس فروش',
            self::ACCOUNTANT => 'حسابدار',
            self::MARKETING => 'مدیر بازاریابی',
            self::DEVELOPER => 'توسعه‌دهنده / یکپارچه‌ساز',
        };
    }
}
