<?php

namespace App\Services;

use App\Models\KubernetesApp;
use App\Models\KubernetesCluster;
use Illuminate\Support\Str;

class KubernetesManifestGenerator
{
    private KubernetesApp $app;
    private KubernetesCluster $cluster;
    private array $envVars = [];
    private array $secrets = [];

    public function __construct(KubernetesApp $app, KubernetesCluster $cluster)
    {
        $this->app = $app;
        $this->cluster = $cluster;
    }

    public function setEnvVars(array $envVars): self
    {
        $this->envVars = $envVars;
        return $this;
    }

    public function setSecrets(array $secrets): self
    {
        $this->secrets = $secrets;
        return $this;
    }

    /**
     * Generate all manifests for an app deployment
     */
    public function generateAll(): array
    {
        return [
            'deployment' => $this->generateDeployment(),
            'service' => $this->generateService(),
            'ingress' => $this->generateIngress(),
            'hpa' => $this->app->autoscale_enabled ? $this->generateHPA() : null,
            'configmap' => $this->generateConfigMap(),
            'secret' => !empty($this->secrets) ? $this->generateSecret() : null,
        ];
    }

    /**
     * Generate Deployment manifest
     */
    public function generateDeployment(): array
    {
        $resources = $this->app->getPodResources();

        $manifest = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => [
                'name' => $this->app->name,
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                    'app.kubernetes.io/name' => $this->app->name,
                    'app.kubernetes.io/version' => $this->app->image_tag,
                ],
                'annotations' => [
                    'coolify.app.uuid' => $this->app->uuid,
                ],
            ],
            'spec' => [
                'replicas' => $this->app->replicas,
                'selector' => [
                    'matchLabels' => [
                        'app' => $this->app->name,
                    ],
                ],
                'strategy' => [
                    'type' => 'RollingUpdate',
                    'rollingUpdate' => [
                        'maxSurge' => 1,
                        'maxUnavailable' => 0,
                    ],
                ],
                'template' => [
                    'metadata' => [
                        'labels' => [
                            'app' => $this->app->name,
                            'managed-by' => 'coolify',
                        ],
                    ],
                    'spec' => [
                        'containers' => [
                            $this->generateContainerSpec($resources),
                        ],
                    ],
                ],
            ],
        ];

        // Add init containers if build strategy requires
        if ($this->app->buildstrategy === 'kaniko') {
            $manifest['spec']['template']['spec']['initContainers'] = [
                $this->generateKanikoInitContainer(),
            ];
        }

        return $manifest;
    }

    private function generateContainerSpec(array $resources): array
    {
        $container = [
            'name' => $this->app->name,
            'image' => $this->app->getFullImageName(),
            'ports' => [
                [
                    'containerPort' => $this->app->container_port,
                    'protocol' => 'TCP',
                ],
            ],
            'env' => $this->generateEnvVars(),
            'resources' => [
                'requests' => [
                    'cpu' => $resources['cpu'],
                    'memory' => $resources['memory'],
                ],
                'limits' => [
                    'cpu' => $this->doubleResource($resources['cpu']),
                    'memory' => $this->doubleResource($resources['memory']),
                ],
            ],
            'livenessProbe' => null,
            'readinessProbe' => null,
        ];

        // Add healthcheck if enabled
        if ($this->app->healthcheck_enabled) {
            $container['livenessProbe'] = $this->generateProbe('liveness');
            $container['readinessProbe'] = $this->generateProbe('readiness');
        }

        return $container;
    }

    private function generateProbe(string $type): array
    {
        $port = $this->app->healthcheck_port ?? $this->app->container_port;
        $path = $this->app->healthcheck_path ?? '/';

        $probe = [
            'httpGet' => [
                'path' => $path,
                'port' => $port,
            ],
            'initialDelaySeconds' => 15,
            'periodSeconds' => 10,
            'timeoutSeconds' => 5,
            'failureThreshold' => 3,
        ];

        return $probe;
    }

    private function generateEnvVars(): array
    {
        $env = [];

        // Add configured env vars
        foreach ($this->envVars as $key => $value) {
            $env[] = [
                'name' => $key,
                'value' => (string) $value,
            ];
        }

        // Add app-specific env vars
        $env[] = [
            'name' => 'APP_NAME',
            'value' => $this->app->name,
        ];
        $env[] = [
            'name' => 'APP_NAMESPACE',
            'value' => $this->app->namespace,
        ];

        return $env;
    }

    private function generateKanikoInitContainer(): array
    {
        return [
            'name' => 'kaniko-init',
            'image' => 'gcr.io/kaniko-project/executor:v1.15.0',
            'command' => ['/bin/sh', '-c'],
            'args' => [
                "echo 'Building image...' && " .
                "echo \$GIT_REPO && " .
                "echo 'Build would happen here in real Kaniko build'",
            ],
            'env' => [
                [
                    'name' => 'GIT_REPO',
                    'value' => $this->app->pipeline?->git_repository ?? 'N/A',
                ],
            ],
            'resources' => [
                'requests' => ['cpu' => '100m', 'memory' => '128Mi'],
                'limits' => ['cpu' => '500m', 'memory' => '512Mi'],
            ],
        ];
    }

    /**
     * Generate Service manifest
     */
    public function generateService(): array
    {
        return [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => [
                'name' => $this->app->name,
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                ],
            ],
            'spec' => [
                'type' => 'ClusterIP',
                'ports' => [
                    [
                        'port' => $this->app->container_port,
                        'targetPort' => $this->app->container_port,
                        'protocol' => 'TCP',
                        'name' => 'http',
                    ],
                ],
                'selector' => [
                    'app' => $this->app->name,
                ],
            ],
        ];
    }

    /**
     * Generate Ingress manifest
     */
    public function generateIngress(): ?array
    {
        if (empty($this->app->ingress_host)) {
            return null;
        }

        $ingressClass = 'nginx'; // default, should be configurable

        $manifest = [
            'apiVersion' => 'networking.k8s.io/v1',
            'kind' => 'Ingress',
            'metadata' => [
                'name' => $this->app->name,
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                ],
                'annotations' => [
                    'kubernetes.io/ingress.class' => $ingressClass,
                    'nginx.ingress.kubernetes.io/proxy-body-size' => '100m',
                    'nginx.ingress.kubernetes.io/proxy-read-timeout' => '300',
                    'nginx.ingress.kubernetes.io/proxy-send-timeout' => '300',
                ],
            ],
            'spec' => [
                'rules' => [
                    [
                        'host' => $this->app->ingress_host,
                        'http' => [
                            'paths' => [
                                [
                                    'path' => $this->app->ingress_path ?: '/',
                                    'pathType' => 'Prefix',
                                    'backend' => [
                                        'service' => [
                                            'name' => $this->app->name,
                                            'port' => [
                                                'number' => $this->app->container_port,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Add TLS if enabled
        if ($this->app->ingress_tls) {
            $manifest['spec']['tls'] = [
                [
                    'hosts' => [$this->app->ingress_host],
                    'secretName' => $this->app->name . '-tls',
                ],
            ];
        }

        return $manifest;
    }

    /**
     * Generate HPA (Horizontal Pod Autoscaler) manifest
     */
    public function generateHPA(): array
    {
        return [
            'apiVersion' => 'autoscaling/v2',
            'kind' => 'HorizontalPodAutoscaler',
            'metadata' => [
                'name' => $this->app->name,
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                ],
            ],
            'spec' => [
                'scaleTargetRef' => [
                    'apiVersion' => 'apps/v1',
                    'kind' => 'Deployment',
                    'name' => $this->app->name,
                ],
                'minReplicas' => $this->app->autoscale_min,
                'maxReplicas' => $this->app->autoscale_max,
                'metrics' => [
                    [
                        'type' => 'Resource',
                        'resource' => [
                            'name' => 'cpu',
                            'target' => [
                                'type' => 'Utilization',
                                'averageUtilization' => $this->app->autoscale_cpu_threshold,
                            ],
                        ],
                    ],
                    [
                        'type' => 'Resource',
                        'resource' => [
                            'name' => 'memory',
                            'target' => [
                                'type' => 'Utilization',
                                'averageUtilization' => $this->app->autoscale_memory_threshold,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate ConfigMap manifest
     */
    public function generateConfigMap(): array
    {
        $data = [];

        // Add env vars that are not secrets
        foreach ($this->envVars as $key => $value) {
            if (!in_array($key, array_keys($this->secrets))) {
                $data[$key] = (string) $value;
            }
        }

        $data['APP_NAME'] = $this->app->name;
        $data['APP_NAMESPACE'] = $this->app->namespace;

        return [
            'apiVersion' => 'v1',
            'kind' => 'ConfigMap',
            'metadata' => [
                'name' => $this->app->name . '-config',
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                ],
            ],
            'data' => $data,
        ];
    }

    /**
     * Generate Secret manifest
     */
    public function generateSecret(): array
    {
        $data = [];

        foreach ($this->secrets as $key => $value) {
            $data[$key] = base64_encode((string) $value);
        }

        return [
            'apiVersion' => 'v1',
            'kind' => 'Secret',
            'metadata' => [
                'name' => $this->app->name . '-secrets',
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                ],
            ],
            'type' => 'Opaque',
            'data' => $data,
        ];
    }

    /**
     * Helper to double resource values
     */
    private function doubleResource(string $value): string
    {
        // Handle CPU: if it's in millicores (e.g., "100m"), double it
        if (str_ends_with($value, 'm')) {
            $num = (int) rtrim($value, 'm');
            return ($num * 2) . 'm';
        }

        // Handle memory: if it ends with Mi/Gi/etc, try to double
        if (preg_match('/^(\d+)(Mi|Gi|Ki|M|G|K)$/', $value, $matches)) {
            $num = (int) $matches[1];
            $unit = $matches[2];
            return ($num * 2) . $unit;
        }

        return $value;
    }

    /**
     * Generate a rollback manifest for a previous version
     */
    public function generateRollback(string $fromRevision, string $toRevision): array
    {
        return [
            'apiVersion' => 'batch/v1',
            'kind' => 'Job',
            'metadata' => [
                'name' => 'rollback-' . $this->app->name . '-' . Str::random(8),
                'namespace' => $this->app->namespace,
                'labels' => [
                    'app' => $this->app->name,
                    'managed-by' => 'coolify',
                    'type' => 'rollback',
                ],
            ],
            'spec' => [
                'ttlSecondsAfterFinished' => 300,
                'template' => [
                    'metadata' => [
                        'labels' => [
                            'app' => $this->app->name,
                        ],
                    ],
                    'spec' => [
                        'restartPolicy' => 'Never',
                        'containers' => [
                            [
                                'name' => 'rollback',
                                'image' => 'bitnami/kubectl:latest',
                                'command' => [
                                    'kubectl',
                                    'rollout',
                                    'undo',
                                    'deployment/' . $this->app->name,
                                    '--to-revision=' . $toRevision,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
