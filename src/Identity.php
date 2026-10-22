<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/** The signed-in PlainShow user, built from verified token claims. */
final class Identity
{
    /** @param array<string, mixed> $claims */
    public function __construct(private readonly array $claims)
    {
        if (!isset($claims['sub']) || !ctype_digit((string) $claims['sub'])) {
            throw new KitException('Identity has no valid subject.');
        }
    }

    public function id(): int { return (int) $this->claims['sub']; }
    public function username(): string { return (string) ($this->claims['preferred_username'] ?? ''); }
    public function firstName(): string { return (string) ($this->claims['given_name'] ?? ''); }
    public function lastName(): string { return (string) ($this->claims['family_name'] ?? ''); }
    public function name(): string { return trim((string) ($this->claims['name'] ?? ($this->firstName() . ' ' . $this->lastName()))) ?: $this->username(); }
    public function email(): ?string { return isset($this->claims['email']) ? (string) $this->claims['email'] : null; }
    public function picture(): ?string { return isset($this->claims['picture']) ? (string) $this->claims['picture'] : null; }
    public function role(): string { return (string) ($this->claims['ps_role'] ?? 'user'); }
    public function piUser(): ?string { return isset($this->claims['ps_pi_user']) ? (string) $this->claims['ps_pi_user'] : null; }
    public function isAdmin(): bool { return $this->role() === 'admin'; }
    public function isModerator(): bool { return in_array($this->role(), ['admin', 'moderator'], true); }

    /** @return list<string> */
    public function services(): array { return array_values(array_map('strval', (array) ($this->claims['ps_services'] ?? []))); }

    /** @return list<string> */
    public function capabilities(): array { return array_values(array_map('strval', (array) ($this->claims['ps_caps'] ?? []))); }

    public function hasService(string $extension): bool
    {
        return in_array($extension, $this->services(), true);
    }

    /** `*` grants everything; `blog.*` grants every blog capability. */
    public function can(string $capability): bool
    {
        foreach ($this->capabilities() as $granted) {
            if ($granted === '*' || $granted === $capability) {
                return true;
            }
            if (str_ends_with($granted, '.*') && str_starts_with($capability, substr($granted, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    public function initials(): string
    {
        $source = trim($this->firstName() . ' ' . $this->lastName()) ?: $this->username();
        $parts = preg_split('/\s+/u', $source) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials !== '' ? $initials : '?';
    }

    /** @return array<string, mixed> */
    public function claims(): array { return $this->claims; }
}
