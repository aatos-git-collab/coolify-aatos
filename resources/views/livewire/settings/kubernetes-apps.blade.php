<div>
    <x-slot:title>
        Kubernetes Apps | Aatos
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="kubernetes" />

        <div class="w-full">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2>Kubernetes Applications</h2>
                    <p class="text-helper">Deploy and manage apps on Kubernetes</p>
                </div>
                <x-forms.button type="button" wire:click="createApp">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New App
                </x-forms.button>
            </div>

            {{-- Form Modal --}}
            @if ($showForm)
            <div class="p-6 mb-6 bg-theme-card rounded-lg border border-theme max-h-[80vh] overflow-y-auto">
                <h3 class="text-lg font-semibold mb-4">
                    {{ $isEditing ? 'Edit App' : 'Create New K8s App' }}
                </h3>

                <form wire:submit='saveApp' class="space-y-6">
                    {{-- Basic Settings --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Basic</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input wire:model='name' label="App Name" placeholder="my-app" required />
                            </div>
                            <div>
                                <x-forms.select wire:model='cluster_id' label="Cluster">
                                    <option value="">Select cluster...</option>
                                    @foreach($clusters as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-forms.input wire:model='namespace' label="Namespace" placeholder="default" />
                            </div>
                            <div>
                                <x-forms.input wire:model='image_repository' label="Image Repository" placeholder="nginx" />
                            </div>
                            <div>
                                <x-forms.input wire:model='image_tag' label="Image Tag" placeholder="latest" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-forms.input type="number" wire:model='container_port' label="Container Port" />
                            </div>
                            <div>
                                <x-forms.input type="number" wire:model='replicas' label="Replicas" />
                            </div>
                            <div>
                                <x-forms.select wire:model='pod_size' label="Pod Size">
                                    <option value="tiny">Tiny (100m CPU, 128Mi)</option>
                                    <option value="small">Small (250m CPU, 256Mi)</option>
                                    <option value="medium">Medium (500m CPU, 512Mi)</option>
                                    <option value="large">Large (1 CPU, 1Gi)</option>
                                    <option value="xlarge">XLarge (2 CPU, 2Gi)</option>
                                </x-forms.select>
                            </div>
                        </div>
                    </div>

                    {{-- Build Settings --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Build</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.select wire:model='buildstrategy' label="Build Strategy">
                                    <option value="dockerfile">Dockerfile</option>
                                    <option value="kaniko">Kaniko (in-cluster build)</option>
                                    <option value="buildpack">Buildpacks</option>
                                </x-forms.select>
                            </div>
                            <div>
                                <x-forms.input wire:model='dockerfile_path' label="Dockerfile Path" placeholder="Dockerfile" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <x-forms.textarea wire:model='build_commands' label="Build Commands (optional)" rows="3"
                                placeholder="RUN npm install && npm run build" />
                        </div>
                    </div>

                    {{-- Scaling --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Scaling</h4>
                        <div class="flex items-center gap-3 mb-4">
                            <input type="checkbox" wire:model='autoscale_enabled' id="autoscale"
                                class="w-4 h-4 rounded border-theme bg-theme text-accent focus:ring-accent">
                            <label for="autoscale" class="cursor-pointer">Enable Auto-scaling</label>
                        </div>
                        @if ($autoscale_enabled)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <x-forms.input type="number" wire:model='autoscale_min' label="Min Replicas" />
                            </div>
                            <div>
                                <x-forms.input type="number" wire:model='autoscale_max' label="Max Replicas" />
                            </div>
                            <div>
                                <x-forms.input type="number" wire:model='autoscale_cpu_threshold' label="CPU Threshold %" />
                            </div>
                            <div>
                                <x-forms.input type="number" wire:model='autoscale_memory_threshold' label="Memory Threshold %" />
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Healthcheck --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Healthcheck</h4>
                        <div class="flex items-center gap-3 mb-4">
                            <input type="checkbox" wire:model='healthcheck_enabled' id="healthcheck"
                                class="w-4 h-4 rounded border-theme bg-theme text-accent focus:ring-accent">
                            <label for="healthcheck" class="cursor-pointer">Enable Healthcheck</label>
                        </div>
                        @if ($healthcheck_enabled)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input wire:model='healthcheck_path' label="Healthcheck Path" placeholder="/" />
                            </div>
                            <div>
                                <x-forms.input type="number" wire:model='healthcheck_port' label="Healthcheck Port" />
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Ingress --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Ingress</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-forms.input wire:model='ingress_host' label="Host" placeholder="app.example.com" />
                            </div>
                            <div>
                                <x-forms.input wire:model='ingress_path' label="Path" placeholder="/" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model='ingress_tls'
                                        class="w-4 h-4 rounded border-theme bg-theme text-accent focus:ring-accent">
                                    <span>Enable TLS</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Environment Variables --}}
                    <div class="border-b border-theme pb-4">
                        <h4 class="font-medium mb-3 text-sm text-helper uppercase tracking-wider">Environment</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.textarea wire:model='env_vars_json' label="Environment Variables (JSON)"
                                    rows="4" placeholder='{"KEY": "value", "DEBUG": "true"}' />
                            </div>
                            <div>
                                <x-forms.textarea wire:model='secrets_json' label="Secrets (JSON)" rows="4"
                                    placeholder='{"API_KEY": "secret123"}' />
                                <p class="text-xs text-helper mt-1">Secrets are base64 encoded in K8s</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-forms.button type="submit">
                            {{ $isEditing ? 'Update App' : 'Create App' }}
                        </x-forms.button>
                        <x-forms.button type="button" wire:click="cancelForm" class="!bg-gray-500 hover:!bg-gray-600">
                            Cancel
                        </x-forms.button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Apps List --}}
            @if (count($apps) > 0)
            <div class="space-y-4">
                @foreach ($apps as $app)
                <div class="p-6 bg-theme-card rounded-lg border border-theme">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold">{{ $app['name'] }}</h3>
                                <span class="px-2 py-0.5 text-xs rounded
                                    @if($app['status'] === 'deployed') bg-green-500/20 text-green-400
                                    @elseif($app['status'] === 'deploying') bg-blue-500/20 text-blue-400
                                    @elseif($app['status'] === 'failed') bg-red-500/20 text-red-400
                                    @else bg-gray-500/20 text-gray-400 @endif">
                                    {{ $app['status'] }}
                                </span>
                            </div>
                            <p class="text-sm text-helper font-mono">
                                {{ $app['image_repository'] ?? 'N/A' }}:{{ $app['image_tag'] ?? 'latest' }}
                            </p>
                            <div class="flex items-center gap-4 mt-2 text-xs text-helper">
                                <span>Namespace: {{ $app['namespace'] }}</span>
                                <span>Port: {{ $app['container_port'] }}</span>
                                <span>Replicas: {{ $app['replicas'] }}</span>
                                @if ($app['autoscale_enabled'])
                                <span class="text-blue-400">Auto-scale: {{ $app['autoscale_min'] }}-{{ $app['autoscale_max'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-forms.button type="button" wire:click="deployApp('{{ $app['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-green-500 hover:!bg-green-600"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>Deploy</span>
                                <span wire:loading>Deploying...</span>
                            </x-forms.button>

                            @if ($app['status'] === 'deployed')
                            <x-forms.button type="button" wire:click="rollbackApp('{{ $app['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-yellow-500/20 !text-yellow-400 hover:!bg-yellow-500/30"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>Rollback</span>
                                <span wire:loading>Rolling back...</span>
                            </x-forms.button>
                            @endif

                            <x-forms.button type="button" wire:click="checkStatus('{{ $app['id'] }}')"
                                class="!px-3 !py-1 text-xs" wire:loading.attr="disabled">
                                <span wire:loading.remove>Status</span>
                                <span wire:loading>Checking...</span>
                            </x-forms.button>

                            <x-forms.button type="button" wire:click="editApp('{{ $app['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-blue-500 hover:!bg-blue-600">
                                Edit
                            </x-forms.button>

                            <x-forms.button type="button" wire:click="deleteApp('{{ $app['id'] }}')"
                                class="!px-3 !py-1 text-xs !bg-red-500 hover:!bg-red-600"
                                wire:confirm="Delete this app from Kubernetes?">
                                Delete
                            </x-forms.button>
                        </div>
                    </div>

                    {{-- Status Message --}}
                    @if (isset($deployResult) && $loop->first)
                    <div class="mt-3 p-2 rounded text-sm">
                        @if (str_starts_with($deployResult, 'success:'))
                        <span class="text-green-400">{{ str_replace('success:', '', $deployResult) }}</span>
                        @else
                        <span class="text-red-400">{{ str_replace('error:', '', $deployResult) }}</span>
                        @endif
                    </div>
                    @endif

                    @if (isset($statusResult) && $loop->first)
                    <div class="mt-3 p-2 rounded text-sm">
                        @if (str_starts_with($statusResult, 'success:'))
                        <span class="text-green-400">{{ str_replace('success:', '', $statusResult) }}</span>
                        @elseif (str_starts_with($statusResult, 'info:'))
                        <span class="text-blue-400">{{ str_replace('info:', '', $statusResult) }}</span>
                        @else
                        <span class="text-red-400">{{ str_replace('error:', '', $statusResult) }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12 bg-theme-card rounded-lg border border-theme">
                <svg class="w-16 h-16 mx-auto mb-4 text-helper opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <h3 class="text-lg font-semibold mb-2">No Kubernetes Apps</h3>
                <p class="text-helper mb-4">Create your first app to deploy on Kubernetes</p>
                <x-forms.button type="button" wire:click="createApp">
                    Create Your First App
                </x-forms.button>
            </div>
            @endif

            {{-- Info Box --}}
            <div class="mt-6 p-4 bg-theme-card rounded-lg border border-theme">
                <h4 class="font-semibold mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Getting Started
                </h4>
                <ol class="text-sm text-helper space-y-1 list-decimal list-inside">
                    <li>Add a Kubernetes cluster in Settings → Kubernetes</li>
                    <li>Create a new K8s App with your image and configuration</li>
                    <li>Click Deploy to push to your cluster</li>
                    <li>Monitor status and scale automatically if enabled</li>
                </ol>
            </div>
        </div>
    </div>
</div>
