<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Client;

/**
 * The browser half of a broadcast connection: what Laravel Echo needs, and nothing a
 * browser shouldn't have. Only the public app key, host and port ever leave the server —
 * never the secret or app id.
 */
final class EchoConfig
{
    /**
     * @param  array<string, mixed>|null  $connection  a `broadcasting.connections.*` entry
     * @return array<string, mixed>|null null for drivers browsers can't connect to (log, null, redis…)
     */
    public static function fromConnection(?array $connection, ?string $clientHost = null, ?int $clientPort = null): ?array
    {
        $driver = $connection['driver'] ?? null;
        $key = $connection['key'] ?? null;
        $options = is_array($connection['options'] ?? null) ? $connection['options'] : [];

        if (! in_array($driver, ['reverb', 'pusher'], true) || ! is_string($key) || $key === '') {
            return null;
        }

        $host = $clientHost ?: (is_string($options['host'] ?? null) && $options['host'] !== '' ? $options['host'] : null);

        // Hosted Pusher's `host` is its REST API (api-<cluster>.pusher.com); browsers use the cluster instead.
        if (! $clientHost && $driver === 'pusher' && $host !== null && preg_match('/^api-[a-z0-9-]+\.pusher\.com$/', $host)) {
            $host = null;
        }
        $port = $clientPort ?: (is_numeric($options['port'] ?? null) ? (int) $options['port'] : null);
        $tls = isset($options['scheme']) ? $options['scheme'] === 'https' : (bool) ($options['useTLS'] ?? true);

        $config = ['broadcaster' => $driver, 'key' => $key, 'forceTLS' => $tls];

        if ($driver === 'pusher' && is_string($options['cluster'] ?? null) && $options['cluster'] !== '') {
            $config['cluster'] = $options['cluster'];
        }

        if ($host !== null) {
            $config['wsHost'] = $host;
            $config['wsPort'] = $port ?? ($tls ? 443 : 80);
            $config['wssPort'] = $port ?? 443;
            $config['enabledTransports'] = $tls ? ['wss', 'ws'] : ['ws', 'wss'];
        } elseif ($driver === 'reverb') {
            // Reverb always needs a host.
            return null;
        }

        return $config;
    }
}
