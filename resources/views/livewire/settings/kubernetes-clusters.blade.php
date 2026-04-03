<div>
    <x-slot:title>
        Kubernetes Clusters | Aatos
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="kubernetes" />

        <div class="w-full">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2>Kubernetes Clusters</h2>
                    <p class="text-helper">Connect and manage your Kubernetes clusters</p>
                </div>
                <x-forms.button type="button" wire:click="createCluster">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Cluster
                </x-forms.button>
            </div>

            {{-- Form Modal --}}
            @if ($showForm)
            <div class="p-6 mb-6 bg-theme-card rounded-lg border border-theme">
                <h3 class="text-lg font-semibold mb-4">
                    {{ $isEditing ? 'Edit Cluster' : 'Add New Cluster' }}
                </h3>

                <form wire:submit='saveCluster' class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-forms.input wire:model='name' label="Cluster Name" placeholder="my-k8s-cluster" required />
                        </div>
                        <div>
                            <x-forms.input wire:model='api_server_url' label="API Server URL" placeholder="https://k8s.example.com:6443" required />
                        </div>
                    </div>

                    <div>
                        <x-forms.textarea wire:model='kubeconfig' label="Kubeconfig (YAML or Base64)" rows="8"
                            placeholder="apiVersion: v1
clusters:
- cluster:
    server: https://k8s.example.com:6443
    certificate-authority-data: LS0t...
contexts:
- context:
    cluster: my-cluster
    user: admin
current-context: my-cluster" />
                        <p class="text-xs text-helper mt-1">Paste your kubeconfig YAML content or base64-encoded string</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-forms.input wire:model='default_namespace' label="Default Namespace" placeholder="default" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model='is_default' class="w-4 h-4 rounded border-theme bg-theme text-accent focus:ring-accent">
                                <span>Set as default cluster</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-4">
                        <x-forms.button type="submit">
                            {{ $isEditing ? 'Update Cluster' : 'Create Cluster' }}
                        </x-forms.button>
                        <x-forms.button type="button" wire:click="cancelForm" class="!bg-gray-500 hover:!bg-gray-600">
                            Cancel
                        </x-forms.button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Clusters List --}}
            @if (count($clusters) > 0)
            <div class="space-y-4">
                @foreach ($clusters as $cluster)
                <div class="p-6 bg-theme-card rounded-lg border border-theme">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold">{{ $cluster['name'] }}</h3>
                                @if ($cluster['is_default'])
                                <span class="px-2 py-0.5 text-xs bg-green-500/20 text-green-400 rounded">Default</span>
                                @endif
                                <span class="px-2 py-0.5 text-xs bg-theme rounded">
                                    {{ $cluster['distribution'] ?? 'self-managed' }}
                                </span>
                            </div>
                            <p class="text-sm text-helper font-mono">{{ $cluster['api_server_url'] }}</p>
                            <div class="flex items-center gap-4 mt-2 text-xs text-helper">
                                <span>Namespace: {{ $cluster['default_namespace'] }}</span>
                                @if ($cluster['version'])
                                <span>v{{ $cluster['version'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-forms.button type="button" wire:click="testConnection('{{ $cluster['id'] }}')"
                                class="!px-3 !py-1 text-xs" wire:loading.attr="disabled">
                                <span wire:loading.remove>Test</span>
                                <span wire:loading>Testing...</span>
                            </x-forms.button>

                            <x-forms.button type="button" wire:click="editCluster('{{ $cluster['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-blue-500 hover:!bg-blue-600">
                                Edit
                            </x-forms.button>

                            @if (!$cluster['is_default'])
                            <x-forms.button type="button" wire:click="setDefault('{{ $cluster['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-gray-500 hover:!bg-gray-600">
                                Set Default
                            </x-forms.button>
                            @endif

                            <x-forms.button type="button" wire:click="deleteCluster('{{ $cluster['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-red-500 hover:!bg-red-600"
                                wire:confirm="Are you sure you want to delete this cluster?">
                                Delete
                            </x-forms.button>
                        </div>
                    </div>

                    {{-- Test Result --}}
                    @if ($testResult && $loop->first === false)
                    <div class="mt-3 p-2 rounded text-sm">
                        @if (str_starts_with($testResult, 'success:'))
                        <span class="text-green-400">{{ str_replace('success:', '', $testResult) }}</span>
                        @else
                        <span class="text-red-400">{{ str_replace('error:', '', $testResult) }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12 bg-theme-card rounded-lg border border-theme">
                <svg class="w-16 h-16 mx-auto mb-4 text-helper opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
                <h3 class="text-lg font-semibold mb-2">No Kubernetes Clusters</h3>
                <p class="text-helper mb-4">Connect your first Kubernetes cluster to start deploying</p>
                <x-forms.button type="button" wire:click="createCluster">
                    Add Your First Cluster
                </x-forms.button>
            </div>
            @endif

            {{-- Info Box --}}
            <div class="mt-6 p-4 bg-theme-card rounded-lg border border-theme">
                <h4 class="font-semibold mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Supported Kubernetes Distributions
                </h4>
                <ul class="text-sm text-helper space-y-1">
                    <li>• Amazon EKS (Elastic Kubernetes Service)</li>
                    <li>• Google GKE (Google Kubernetes Engine)</li>
                    <li>• Azure AKS (Azure Kubernetes Service)</li>
                    <li>• DigitalOcean DOKS</li>
                    <li>• Linode LKE</li>
                    <li>• Self-managed Kubernetes clusters</li>
                </ul>
            </div>
        </div>
    </div>
</div>
