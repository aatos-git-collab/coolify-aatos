<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Exception;
use Log;

class KubernetesCluster extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'kubeconfig',
        'api_server_url',
        'ca_data',
        'token',
        'default_namespace',
        'version',
        'distribution',
        'is_default',
        'team_id',
    ];

    protected $hidden = [
        'kubeconfig',
        'token',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (KubernetesCluster $cluster) {
            if (empty($cluster->uuid)) {
                $cluster->uuid = (string) Str::uuid();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(KubernetesPipeline::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(KubernetesAddon::class);
    }

    public function getKubeconfigDecrypted(): ?array
    {
        if (empty($this->kubeconfig)) {
            return null;
        }

        try {
            $decrypted = decrypt($this->kubeconfig);
            return json_decode($decrypted, true);
        } catch (Exception $e) {
            Log::error('Failed to decode kubeconfig: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test connection to the cluster
     */
    public function testConnection(): bool
    {
        try {
            $kubeconfig = $this->getKubeconfigDecrypted();
            if (!$kubeconfig) {
                return false;
            }

            $token = $kubeconfig['token'] ?? null;
            $caBundle = $kubeconfig['certificate-authority-data'] ?? null;
            $apiServer = $kubeconfig['server'] ?? $this->api_server_url;

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            if ($token) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->timeout(10)
                ->get(rtrim($apiServer, '/') . '/api/v1');

            return $response->successful();
        } catch (Exception $e) {
            Log::error('K8s connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}
