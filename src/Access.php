<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * Works out which services and capabilities a user has. Login uses it to
 * fill the `ps_services` and `ps_caps` claims; core uses it for its admin
 * screens, so both always agree.
 *
 * Rules:
 * - Admins can use every enabled extension and hold `*`.
 * - Otherwise an extension is usable when it is enabled and either the
 *   user's role is in its default access list or the user has an explicit
 *   allow. An explicit deny always wins.
 * - Capabilities are the role's patterns plus the user's allows, minus the
 *   user's denies. Capabilities of extensions the user cannot use are dropped.
 */
final class Access
{
    public const ROLES = ['admin', 'moderator', 'member', 'guest'];

    /**
     * @param list<array{id: string, enabled: bool, default_access: list<string>}> $extensions
     * @param array<string, string> $accessOverrides extension id => allow|deny
     * @param list<string> $rolePatterns capability patterns granted to the role
     * @param array<string, string> $capabilityOverrides capability => allow|deny
     * @return array{services: list<string>, capabilities: list<string>}
     */
    public static function resolve(string $role, array $extensions, array $accessOverrides, array $rolePatterns, array $capabilityOverrides): array
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'guest';
        $services = [];
        foreach ($extensions as $extension) {
            if (!$extension['enabled']) {
                continue;
            }
            $override = $accessOverrides[$extension['id']] ?? null;
            if ($override === 'deny') {
                continue;
            }
            if ($role === 'admin' || $override === 'allow' || in_array($role, $extension['default_access'], true)) {
                $services[] = $extension['id'];
            }
        }

        if ($role === 'admin') {
            return ['services' => $services, 'capabilities' => ['*']];
        }

        $capabilities = $rolePatterns;
        foreach ($capabilityOverrides as $capability => $effect) {
            if ($effect === 'allow') {
                $capabilities[] = $capability;
            }
        }
        $denied = array_keys(array_filter($capabilityOverrides, fn ($effect) => $effect === 'deny'));
        $capabilities = array_values(array_unique(array_filter(
            $capabilities,
            function (string $capability) use ($denied, $services): bool {
                if (in_array($capability, $denied, true)) {
                    return false;
                }
                $extension = strstr($capability, '.', true);
                return $extension === false || $extension === '*' || $extension === 'core' || in_array($extension, $services, true);
            },
        )));

        return ['services' => $services, 'capabilities' => $capabilities];
    }

    /** Default capability patterns per role, used until an admin changes them. */
    public static function defaultRolePatterns(): array
    {
        return [
            'admin' => ['*'],
            'moderator' => ['core.dashboard.use', '*.view', '*.create', '*.moderate'],
            'member' => ['core.dashboard.use', '*.view', '*.create'],
            'guest' => ['core.dashboard.use', '*.view'],
        ];
    }

    /** Expands patterns such as `*.moderate` against the known capability names. */
    public static function expand(array $patterns, array $known): array
    {
        $result = [];
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return ['*'];
            }
            if (!str_contains($pattern, '*')) {
                $result[] = $pattern;
                continue;
            }
            $regex = '/^' . str_replace('\*', '[a-z0-9_-]+', preg_quote($pattern, '/')) . '$/';
            $prefix = str_ends_with($pattern, '.*') ? substr($pattern, 0, -1) : null;
            foreach ($known as $name) {
                // Rules that start with a wildcard never reach core's own
                // capabilities: those run the platform and are granted by name.
                if (str_starts_with($pattern, '*') && str_starts_with($name, 'core.')) {
                    continue;
                }
                // `*.moderate` matches `blog.post.moderate`; `blog.*` matches all of blog.
                if (preg_match($regex, $name) || ($prefix !== null && str_starts_with($name, $prefix)) || self::suffixMatch($pattern, $name)) {
                    $result[] = $name;
                }
            }
        }
        return array_values(array_unique($result));
    }

    private static function suffixMatch(string $pattern, string $name): bool
    {
        if (!str_starts_with($pattern, '*.')) {
            return false;
        }
        return str_ends_with($name, substr($pattern, 1));
    }
}
