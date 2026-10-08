<?php

namespace App\Enums;

enum Role: string
{
    case Staff = 'staff';
    case Manager = 'manager';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Manager => 'Manager',
            self::Admin => 'Admin',
        };
    }

    public function canManageAllTenders(): bool
    {
        return $this !== self::Staff;
    }
}
