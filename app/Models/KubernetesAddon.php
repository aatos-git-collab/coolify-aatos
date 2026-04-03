<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KubernetesAddon extends Model
{
    protected $fillable = [
        'uuid',
        'kubernetes_cluster_id',
        'name',
        'type',
        'namespace',
        'version',
        'size',
        'storage_gb',
        'high_availability',
        'database_name',
        'username',
        'status',
    ];

    protected $casts = [
        'high_availability' => 'boolean',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (KubernetesAddon $addon) {
            if (empty($addon->uuid)) {
                $addon->uuid = (string) Str::uuid();
            }
        });
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KubernetesCluster::class, 'kubernetes_cluster_id');
    }

    /**
     * Get addon templates from ArtifactHub or built-in
     */
    public static function getAvailableTypes(): array
    {
        return [
            'postgresql' => [
                'name' => 'PostgreSQL',
                'icon' => '🐘',
                'category' => 'database',
                'default_port' => 5432,
            ],
            'mysql' => [
                'name' => 'MySQL',
                'icon' => '🐬',
                'category' => 'database',
                'default_port' => 3306,
            ],
            'redis' => [
                'name' => 'Redis',
                'icon' => '🔴',
                'category' => 'cache',
                'default_port' => 6379,
            ],
            'mongodb' => [
                'name' => 'MongoDB',
                'icon' => '🍃',
                'category' => 'database',
                'default_port' => 27017,
            ],
            'rabbitmq' => [
                'name' => 'RabbitMQ',
                'icon' => '🐰',
                'category' => 'messaging',
                'default_port' => 5672,
            ],
            'minio' => [
                'name' => 'MinIO',
                'icon' => '🪣',
                'category' => 'storage',
                'default_port' => 9000,
            ],
        ];
    }
}
