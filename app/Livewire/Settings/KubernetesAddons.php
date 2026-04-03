<?php

namespace App\Livewire\Settings;

use App\Models\KubernetesAddon;
use App\Models\KubernetesCluster;
use App\Models\KubernetesApp;
use App\Services\KubernetesService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Exception;

class KubernetesAddons extends Component
{
    public array $addons = [];
    public array $clusters = [];
    public array $availableTypes = [];
    public bool $showForm = false;
    public bool $isEditing = false;

    // Form fields
    #[Validate('required|string|min:2|max:255')]
    public string $name = '';

    #[Validate('required')]
    public ?string $cluster_id = null;

    #[Validate('required')]
    public string $type = 'postgresql';

    public string $namespace = 'kubero-addons';
    public string $version = 'latest';
    public string $size = 'small';
    public int $storage_gb = 5;
    public bool $high_availability = false;

    // Database fields (for database types)
    public string $database_name = '';
    public string $username = '';

    // State
    public ?string $editingId = null;
    public ?string $deployResult = null;
    public bool $deploying = false;
    public ?string $statusResult = null;

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        $this->availableTypes = KubernetesAddon::getAvailableTypes();
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->addons = KubernetesAddon::with('cluster')
            ->get()
            ->map(fn($addon) => [
                'id' => $addon->id,
                'uuid' => $addon->uuid,
                'name' => $addon->name,
                'type' => $addon->type,
                'type_info' => $this->availableTypes[$addon->type] ?? ['name' => $addon->type, 'icon' => '📦'],
                'namespace' => $addon->namespace,
                'version' => $addon->version,
                'size' => $addon->size,
                'storage_gb' => $addon->storage_gb,
                'high_availability' => $addon->high_availability,
                'database_name' => $addon->database_name,
                'username' => $addon->username,
                'status' => $addon->status,
                'cluster_name' => $addon->cluster?->name,
                'cluster_id' => $addon->kubernetes_cluster_id,
                'created_at' => $addon->created_at?->toISOString(),
            ])
            ->toArray();

        $this->clusters = KubernetesCluster::all()->toArray();
    }

    public function createAddon(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->isEditing = false;
    }

    public function editAddon(string $id): void
    {
        $addon = KubernetesAddon::findOrFail($id);
        $this->editingId = $id;
        $this->name = $addon->name;
        $this->cluster_id = (string) $addon->kubernetes_cluster_id;
        $this->type = $addon->type;
        $this->namespace = $addon->namespace;
        $this->version = $addon->version;
        $this->size = $addon->size;
        $this->storage_gb = $addon->storage_gb;
        $this->high_availability = $addon->high_availability;
        $this->database_name = $addon->database_name ?? '';
        $this->username = $addon->username ?? '';
        $this->isEditing = true;
        $this->showForm = true;
    }

    public function saveAddon(): void
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'kubernetes_cluster_id' => $this->cluster_id,
                'type' => $this->type,
                'namespace' => $this->namespace,
                'version' => $this->version,
                'size' => $this->size,
                'storage_gb' => $this->storage_gb,
                'high_availability' => $this->high_availability,
                'database_name' => $this->type !== 'minio' ? $this->database_name : null,
                'username' => $this->type !== 'minio' ? $this->username : null,
            ];

            if ($this->isEditing && $this->editingId) {
                $addon = KubernetesAddon::findOrFail($this->editingId);
                $addon->update($data);
                $this->dispatch('banner', [
                    'type' => 'success',
                    'message' => 'Addon updated successfully',
                ]);
            } else {
                KubernetesAddon::create($data);
                $this->dispatch('banner', [
                    'type' => 'success',
                    'message' => 'Addon created successfully',
                ]);
            }

            $this->showForm = false;
            $this->loadData();

        } catch (Exception $e) {
            Log::error('Failed to save addon: ' . $e->getMessage());
            $this->dispatch('banner', [
                'type' => 'error',
                'message' => 'Failed to save addon: ' . $e->getMessage(),
            ]);
        }
    }

    public function deployAddon(string $id): void
    {
        $addon = KubernetesAddon::findOrFail($id);
        $cluster = $addon->cluster;

        if (!$cluster) {
            $this->deployResult = 'error: Cluster not found';
            return;
        }

        $this->deploying = true;
        $this->deployResult = null;

        try {
            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            // Test connection
            if (!$k8s->testConnection()) {
                throw new Exception('Cannot connect to cluster');
            }

            // Ensure namespace exists
            if (!$k8s->namespaceExists($addon->namespace)) {
                $k8s->createNamespace($addon->namespace);
            }

            // Generate and deploy based on addon type
            $this->deployAddonManifest($k8s, $addon);

            // Update status
            $addon->update(['status' => 'deployed']);

            $this->deployResult = 'success: Addon deployed successfully';
            $this->loadData();

        } catch (Exception $e) {
            Log::error('Addon deployment failed: ' . $e->getMessage());
            $addon->update(['status' => 'failed']);
            $this->deployResult = 'error: ' . $e->getMessage();
        } finally {
            $this->deploying = false;
        }
    }

    private function deployAddonManifest(KubernetesService $k8s, KubernetesAddon $addon): void
    {
        $typeConfig = $this->availableTypes[$addon->type] ?? [];
        $port = $typeConfig['default_port'] ?? 5432;

        switch ($addon->type) {
            case 'postgresql':
            case 'mysql':
                $this->deployDatabaseAddon($k8s, $addon, $port);
                break;
            case 'redis':
                $this->deployRedisAddon($k8s, $addon, $port);
                break;
            case 'mongodb':
                $this->deployMongoAddon($k8s, $addon, $port);
                break;
            case 'rabbitmq':
                $this->deployRabbitMQAddon($k8s, $addon, $port);
                break;
            case 'minio':
                $this->deployMinioAddon($k8s, $addon, $port);
                break;
            default:
                throw new Exception('Unknown addon type: ' . $addon->type);
        }
    }

    private function deployDatabaseAddon(KubernetesService $k8s, KubernetesAddon $addon, int $port): void
    {
        $name = $addon->name;

        // ConfigMap
        $k8s->createConfigMap($name, [
            'database' => $addon->database_name ?? $name,
        ], $addon->namespace);

        // Secret
        $password = bin2hex(random_bytes(16));
        $k8s->createSecret($name, [
            'username' => $addon->username ?? 'admin',
            'password' => $password,
            'database' => $addon->database_name ?? $name,
        ], $addon->namespace);

        // PVC for persistence
        $storageRequest = [
            'storageClassName' => 'standard',
            'resources' => ['requests' => ['storage' => $addon->storage_gb . 'Gi']],
            'accessModes' => ['ReadWriteOnce'],
        ];
        $k8s->createPersistentVolumeClaim($name, $storageRequest, $addon->namespace);

        // Deployment
        $image = $addon->type === 'postgresql'
            ? 'postgres:' . ($addon->version === 'latest' ? '15' : $addon->version)
            : 'mysql:' . ($addon->version === 'latest' ? '8' : $addon->version);

        $deployment = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'replicas' => $addon->high_availability ? 3 : 1,
                'selector' => ['matchLabels' => ['app' => $name]],
                'template' => [
                    'metadata' => ['labels' => ['app' => $name]],
                    'spec' => [
                        'containers' => [[
                            'name' => $name,
                            'image' => $image,
                            'ports' => [['containerPort' => $port]],
                            'env' => [
                                ['name' => 'POSTGRES_DB', 'value' => $addon->database_name ?? $name],
                                ['name' => 'POSTGRES_USER', 'value' => $addon->username ?? 'admin'],
                                ['name' => 'POSTGRES_PASSWORD', 'valueFrom' => ['secretKeyRef' => ['name' => $name, 'key' => 'password']]],
                            ],
                            'volumeMounts' => [['name' => 'data', 'mountPath' => '/var/lib/' . ($addon->type === 'postgresql' ? 'postgres' : 'mysql')]],
                        ]],
                        'volumes' => [['name' => 'data', 'persistentVolumeClaim' => ['claimName' => $name]]],
                    ],
                ],
            ],
        ];

        $k8s->createDeployment($deployment);

        // Service
        $service = [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'selector' => ['app' => $name],
                'ports' => [['port' => $port, 'targetPort' => $port]],
                'type' => $addon->high_availability ? 'ClusterIP' : 'LoadBalancer',
            ],
        ];

        $k8s->createService($service);
    }

    private function deployRedisAddon(KubernetesService $k8s, KubernetesAddon $addon, int $port): void
    {
        $name = $addon->name;

        // Secret
        $password = bin2hex(random_bytes(16));
        $k8s->createSecret($name, ['password' => $password], $addon->namespace);

        // PVC
        $storageRequest = [
            'storageClassName' => 'standard',
            'resources' => ['requests' => ['storage' => $addon->storage_gb . 'Gi']],
            'accessModes' => ['ReadWriteOnce'],
        ];
        $k8s->createPersistentVolumeClaim($name, $storageRequest, $addon->namespace);

        // Deployment
        $deployment = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'replicas' => $addon->high_availability ? 3 : 1,
                'selector' => ['matchLabels' => ['app' => $name]],
                'template' => [
                    'metadata' => ['labels' => ['app' => $name]],
                    'spec' => [
                        'containers' => [[
                            'name' => $name,
                            'image' => 'redis:' . ($addon->version === 'latest' ? '7' : $addon->version),
                            'ports' => [['containerPort' => $port]],
                            'command' => ['redis-server', '--requirepass', $password],
                            'volumeMounts' => [['name' => 'data', 'mountPath' => '/data']],
                        ]],
                        'volumes' => [['name' => 'data', 'persistentVolumeClaim' => ['claimName' => $name]]],
                    ],
                ],
            ],
        ];

        $k8s->createDeployment($deployment);

        // Service
        $service = [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'selector' => ['app' => $name],
                'ports' => [['port' => $port, 'targetPort' => $port]],
                'type' => 'ClusterIP',
            ],
        ];

        $k8s->createService($service);
    }

    private function deployMongoAddon(KubernetesService $k8s, KubernetesAddon $addon, int $port): void
    {
        $name = $addon->name;

        // Secret
        $password = bin2hex(random_bytes(16));
        $k8s->createSecret($name, [
            'username' => $addon->username ?? 'admin',
            'password' => $password,
        ], $addon->namespace);

        // PVC
        $storageRequest = [
            'storageClassName' => 'standard',
            'resources' => ['requests' => ['storage' => $addon->storage_gb . 'Gi']],
            'accessModes' => ['ReadWriteOnce'],
        ];
        $k8s->createPersistentVolumeClaim($name, $storageRequest, $addon->namespace);

        // Deployment
        $deployment = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'replicas' => $addon->high_availability ? 3 : 1,
                'selector' => ['matchLabels' => ['app' => $name]],
                'template' => [
                    'metadata' => ['labels' => ['app' => $name]],
                    'spec' => [
                        'containers' => [[
                            'name' => $name,
                            'image' => 'mongo:' . ($addon->version === 'latest' ? '7' : $addon->version),
                            'ports' => [['containerPort' => $port]],
                            'env' => [
                                ['name' => 'MONGO_INITDB_ROOT_USERNAME', 'value' => $addon->username ?? 'admin'],
                                ['name' => 'MONGO_INITDB_ROOT_PASSWORD', 'valueFrom' => ['secretKeyRef' => ['name' => $name, 'key' => 'password']]],
                            ],
                            'volumeMounts' => [['name' => 'data', 'mountPath' => '/data/db']],
                        ]],
                        'volumes' => [['name' => 'data', 'persistentVolumeClaim' => ['claimName' => $name]]],
                    ],
                ],
            ],
        ];

        $k8s->createDeployment($deployment);

        // Service
        $service = [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'selector' => ['app' => $name],
                'ports' => [['port' => $port, 'targetPort' => $port]],
                'type' => $addon->high_availability ? 'ClusterIP' : 'LoadBalancer',
            ],
        ];

        $k8s->createService($service);
    }

    private function deployRabbitMQAddon(KubernetesService $k8s, KubernetesAddon $addon, int $port): void
    {
        $name = $addon->name;

        // Secret
        $password = bin2hex(random_bytes(16));
        $k8s->createSecret($name, [
            'username' => $addon->username ?? 'admin',
            'password' => $password,
        ], $addon->namespace);

        // Deployment
        $deployment = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'replicas' => $addon->high_availability ? 3 : 1,
                'selector' => ['matchLabels' => ['app' => $name]],
                'template' => [
                    'metadata' => ['labels' => ['app' => $name]],
                    'spec' => [
                        'containers' => [[
                            'name' => $name,
                            'image' => 'rabbitmq:' . ($addon->version === 'latest' ? '3.12' : $addon->version),
                            'ports' => [['containerPort' => $port], ['containerPort' => 15672]],
                            'env' => [
                                ['name' => 'RABBITMQ_DEFAULT_USER', 'value' => $addon->username ?? 'admin'],
                                ['name' => 'RABBITMQ_DEFAULT_PASS', 'valueFrom' => ['secretKeyRef' => ['name' => $name, 'key' => 'password']]],
                            ],
                        ]],
                    ],
                ],
            ],
        ];

        $k8s->createDeployment($deployment);

        // Service (with management port)
        $service = [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'selector' => ['app' => $name],
                'ports' => [
                    ['name' => 'amqp', 'port' => 5672, 'targetPort' => 5672],
                    ['name' => 'management', 'port' => 15672, 'targetPort' => 15672],
                ],
                'type' => 'LoadBalancer',
            ],
        ];

        $k8s->createService($service);
    }

    private function deployMinioAddon(KubernetesService $k8s, KubernetesAddon $addon, int $port): void
    {
        $name = $addon->name;

        // Secret
        $accessKey = bin2hex(random_bytes(8));
        $secretKey = bin2hex(random_bytes(16));
        $k8s->createSecret($name, [
            'accesskey' => $accessKey,
            'secretkey' => $secretKey,
        ], $addon->namespace);

        // PVC
        $storageRequest = [
            'storageClassName' => 'standard',
            'resources' => ['requests' => ['storage' => $addon->storage_gb . 'Gi']],
            'accessModes' => ['ReadWriteOnce'],
        ];
        $k8s->createPersistentVolumeClaim($name, $storageRequest, $addon->namespace);

        // Deployment
        $deployment = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'replicas' => $addon->high_availability ? 4 : 1,
                'selector' => ['matchLabels' => ['app' => $name]],
                'template' => [
                    'metadata' => ['labels' => ['app' => $name]],
                    'spec' => [
                        'containers' => [[
                            'name' => $name,
                            'image' => 'minio/minio:' . ($addon->version === 'latest' ? 'latest' : $addon->version),
                            'ports' => [['containerPort' => $port]],
                            'command' => ['minio', 'server', '/data', '--console-address', ':9001'],
                            'env' => [
                                ['name' => 'MINIO_ROOT_ACCESS_KEY', 'valueFrom' => ['secretKeyRef' => ['name' => $name, 'key' => 'accesskey']]],
                                ['name' => 'MINIO_ROOT_SECRET_KEY', 'valueFrom' => ['secretKeyRef' => ['name' => $name, 'key' => 'secretkey']]],
                            ],
                            'volumeMounts' => [['name' => 'data', 'mountPath' => '/data']],
                        ]],
                        'volumes' => [['name' => 'data', 'persistentVolumeClaim' => ['claimName' => $name]]],
                    ],
                ],
            ],
        ];

        $k8s->createDeployment($deployment);

        // Service
        $service = [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => ['name' => $name, 'namespace' => $addon->namespace],
            'spec' => [
                'selector' => ['app' => $name],
                'ports' => [
                    ['name' => 'api', 'port' => 9000, 'targetPort' => 9000],
                    ['name' => 'console', 'port' => 9001, 'targetPort' => 9001],
                ],
                'type' => 'LoadBalancer',
            ],
        ];

        $k8s->createService($service);
    }

    public function deleteAddon(string $id): void
    {
        try {
            $addon = KubernetesAddon::findOrFail($id);
            $cluster = $addon->cluster;

            if ($cluster) {
                $k8s = new KubernetesService();
                $k8s->setCluster($cluster);

                // Delete K8s resources if cluster is accessible
                try {
                    $k8s->deleteDeployment($addon->name, $addon->namespace);
                    $k8s->deleteService($addon->name, $addon->namespace);
                    $k8s->deletePersistentVolumeClaim($addon->name, $addon->namespace);
                    $k8s->deleteSecret($addon->name, $addon->namespace);
                    $k8s->deleteConfigMap($addon->name, $addon->namespace);
                } catch (Exception $e) {
                    Log::warning('Failed to cleanup K8s resources: ' . $e->getMessage());
                }
            }

            $addon->delete();

            $this->dispatch('banner', [
                'type' => 'success',
                'message' => 'Addon deleted successfully',
            ]);

            $this->loadData();

        } catch (Exception $e) {
            Log::error('Failed to delete addon: ' . $e->getMessage());
            $this->dispatch('banner', [
                'type' => 'error',
                'message' => 'Failed to delete addon: ' . $e->getMessage(),
            ]);
        }
    }

    public function checkStatus(string $id): void
    {
        try {
            $addon = KubernetesAddon::findOrFail($id);
            $cluster = $addon->cluster;

            if (!$cluster) {
                $this->statusResult = 'error: Cluster not found';
                return;
            }

            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            $deployment = $k8s->getDeployment($addon->name, $addon->namespace);
            $service = $k8s->getService($addon->name, $addon->namespace);

            $status = $deployment['status'] ?? null;
            $available = $status['availableReplicas'] ?? 0;
            $ready = $status['replicas'] ?? 0;

            if ($ready === 0) {
                $this->statusResult = 'Pending (0/' . ($status['replicas'] ?? '?') . ' replicas ready)';
            } elseif ($available == $ready) {
                $this->statusResult = 'Ready (' . $available . ' replicas)';
            } else {
                $this->statusResult = 'Updating (' . $available . '/' . $ready . ' ready)';
            }

        } catch (Exception $e) {
            $this->statusResult = 'error: ' . $e->getMessage();
        }
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->cluster_id = null;
        $this->type = 'postgresql';
        $this->namespace = 'kubero-addons';
        $this->version = 'latest';
        $this->size = 'small';
        $this->storage_gb = 5;
        $this->high_availability = false;
        $this->database_name = '';
        $this->username = '';
        $this->editingId = null;
        $this->isEditing = false;
        $this->deployResult = null;
        $this->statusResult = null;
    }

    public function render()
    {
        return view('livewire.settings.kubernetes-addons');
    }
}
