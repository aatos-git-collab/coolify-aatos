<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KubernetesPipeline extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'environment_id',
        'kubernetes_cluster_id',
        'name',
        'domain',
        'git_provider',
        'git_repository',
        'git_branch',
        'buildstrategy',
        'reviewapps_enabled',
        'phases',
        'settings',
    ];

    protected $casts = [
        'reviewapps_enabled' => 'boolean',
        'phases' => 'array',
        'settings' => 'array',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (KubernetesPipeline $pipeline) {
            if (empty($pipeline->uuid)) {
                $pipeline->uuid = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KubernetesCluster::class, 'kubernetes_cluster_id');
    }

    public function apps(): HasMany
    {
        return $this->hasMany(KubernetesApp::class, 'kubernetes_pipeline_id');
    }
}
