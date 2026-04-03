<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwarmDomainMapping extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'port' => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function server()
    {
        return $this->application?->destination?->server;
    }

    public function getTraefikLabels(): array
    {
        $identifier = $this->application->swarm_service_identifier ?? $this->application->uuid;

        $labels = [
            'traefik.enable' => 'true',
            'traefik.http.routers.'.$identifier.'.rule' => 'Host(`'.$this->domain.'`)',
            'traefik.http.routers.'.$identifier.'.entrypoints' => 'web',
            'traefik.http.services.'.$identifier.'.loadbalancer.server.port' => (string) $this->port,
        ];

        if ($this->path_prefix && $this->path_prefix !== '/') {
            $labels['traefik.http.routers.'.$identifier.'.rule'] .= ' && PathPrefix(`'.$this->path_prefix.'`)';
        }

        // SSL/TLS
        if ($this->scheme === 'https') {
            $labels['traefik.http.routers.'.$identifier.'-secure.entrypoints'] = 'websecure';
            $labels['traefik.http.routers.'.$identifier.'-secure.tls'] = 'true';
        }

        // Custom Middlewares
        $middlewares = [];

        // Rate Limiting
        if ($this->rate_limit_average) {
            $middlewareName = $identifier.'-ratelimit';
            $labels['traefik.http.middlewares.'.$middlewareName.'.ratelimit.average'] = (string) $this->rate_limit_average;
            $labels['traefik.http.middlewares.'.$middlewareName.'.ratelimit.period'] = $this->rate_limit_period ?? '1m';
            if ($this->rate_limit_burst) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.ratelimit.burst'] = (string) $this->rate_limit_burst;
            }
            $middlewares[] = $middlewareName;
        }

        // Custom Headers (Security)
        if ($this->enable_security_headers) {
            $middlewareName = $identifier.'-security';
            if ($this->header_xss_filter) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.headers.browserXssFilter'] = 'true';
            }
            if ($this->header_content_type_nosniff) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.headers.contentTypeNosniff'] = 'true';
            }
            if ($this->header_frame_deny) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.headers.frameDeny'] = 'true';
            }
            if ($this->header_sts_seconds) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.headers.stsSeconds'] = (string) $this->header_sts_seconds;
            }
            if ($this->header_sts_include_subdomains) {
                $labels['traefik.http.middlewares.'.$middlewareName.'.headers.stsIncludeSubdomains'] = 'true';
            }
            $middlewares[] = $middlewareName;
        }

        // IP Whitelist
        if ($this->ip_whitelist_enabled && $this->ip_whitelist_sources) {
            $middlewareName = $identifier.'-ipallowlist';
            $labels['traefik.http.middlewares.'.$middlewareName.'.ipallowlist.sourcerange'] = $this->ip_whitelist_sources;
            $middlewares[] = $middlewareName;
        }

        // Add middlewares to router
        if (! empty($middlewares)) {
            $labels['traefik.http.routers.'.$identifier.'.middlewares'] = implode(',', $middlewares);
            if ($this->scheme === 'https') {
                $labels['traefik.http.routers.'.$identifier.'-secure.middlewares'] = implode(',', $middlewares);
            }
        }

        // Built-in gzip (always include)
        if (! isset($labels['traefik.http.routers.'.$identifier.'.middlewares'])) {
            $labels['traefik.http.routers.'.$identifier.'.middlewares'] = 'gzip';
        }

        return $labels;
    }
}