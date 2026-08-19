<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a staff member authenticates.
 *
 * Derived from the email domain during the staff sync: `@nattura.is` → Entra,
 * anything else → Password. `Both` is set only when a password account later links
 * an Entra identity (phase 1C) — it is never produced by {@see self::fromEmail()}.
 */
enum AuthMethod: string
{
    case Entra = 'entra';
    case Password = 'password';
    case Both = 'both';

    /**
     * Derive the auth method from an email address. A missing email defaults to
     * Password (those staff are onboarded by invitation; they have no Microsoft account).
     */
    public static function fromEmail(?string $email): self
    {
        $email = $email === null ? '' : strtolower(trim($email));

        return str_ends_with($email, '@nattura.is') ? self::Entra : self::Password;
    }

    public function label(): string
    {
        return __("enums.auth_method.{$this->value}");
    }

    public function canUseEntra(): bool
    {
        return $this === self::Entra || $this === self::Both;
    }

    public function canUsePassword(): bool
    {
        return $this === self::Password || $this === self::Both;
    }
}
