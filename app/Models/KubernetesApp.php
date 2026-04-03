<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KubernetesApp extends Model
{
    protected $fillable = [
        'uuid',
        'kubernetes_pipeline_id',
        'name',
        'namespace',
        'image_repository',
        'image_tag',
        'container_port',
        'replicas',
        'pod_size',
        'buildstrategy',
        'dockerfile_path',
        'build_commands',
        'autoscale_enabled',
        'autoscale_min',
        'autoscale_max',
        'autoscale_cpu_threshold',
        'autoscale_memory_threshold',
        'healthcheck_enabled',
        'healthcheck_path',
        'healthcheck_port',
        'ingress_host',
        'ingress_path',
        'ingress_tls',
        'env_vars',
        'secrets',
        'status',
        'kubernetes_resource_version',
    ];

    protected $casts = [
        'autoscale_enabled' => 'boolean',
        'healthcheck_enabled' => 'boolean',
        'ingress_tls' => 'boolean',
        'env_vars' => 'array',
        'secrets' => 'array',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (KubernetesApp $app) {
            if (empty($app->uuid)) {
                $app->uuid = (string) Str::uuid();
            }
        });
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(KubernetesPipeline::class, 'kubernetes_pipeline_id');
    }

    /**
     * Get full image name
     */
    public function getFullImageName(): string
    {
        return $this->image_repository . ':' . $this->image_tag;
    }

    /**
     * Get pod size resource requests/limits
     */
    public function getPodResources(): array
    {
        $sizes = [
            'tiny' => ['cpu' => '100m', 'memory' => '128Mi'],
            'small' => ['cpu' => '250m', 'memory' => '256Mi'],
            'medium' => ['cpu' => '500m', 'memory' => '512Mi'],
            'large' => ['cpu' => '1', 'memory' => '1Gi'],
            'xlarge' => ['cpu' => '2', 'memory' => '2Gi'],
        ];

        return $sizes[$this->pod_size] ?? $sizes['small'];
    }
}
