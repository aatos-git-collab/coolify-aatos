<div>
    <x-slot:title>
        Kubernetes Addons | Aatos
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="kubernetes-addons" />
        <div class="w-full">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2>Kubernetes Addons</h2>
                    <p class="text-helper">Database & middleware services for your K8s clusters</p>
                </div>
                <x-forms.button type="button" wire:click="createAddon">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Addon
                </x-forms.button>
            </div>

            {{-- Addon Types Info --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                @foreach ($availableTypes as $key => $type)
                <div class="p-3 bg-theme-card rounded-lg border border-theme text-center">
                    <div class="text-2xl mb-1">{{ $type['icon'] }}</div>
                    <div class="text-sm font-medium">{{ $type['name'] }}</div>
                    <div class="text-xs text-helper">{{ $type['category'] }}</div>
                </div>
                @endforeach
            </div>

            {{-- Addons List --}}
            @if (count($addons) > 0)
            <div class="bg-theme-card rounded-lg border border-theme overflow-hidden">
                <table class="w-full">
                    <thead class="bg-theme-hover">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-helper">Addon</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-helper">Type</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-helper">Cluster</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-helper">Namespace</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-helper">Status</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-helper">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme">
                        @foreach ($addons as $addon)
                        <tr class="hover:bg-theme-hover">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $addon['name'] }}</div>
                                <div class="text-xs text-helper">{{ $addon['size'] }} · {{ $addon['storage_gb'] }}GB</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span>{{ $addon['type_info']['icon'] ?? '📦' }}</span>
                                    <span class="text-sm">{{ $addon['type_info']['name'] ?? $addon['type'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $addon['cluster_name'] ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-helper">{{ $addon['namespace'] }}</td>
                            <td class="px-4 py-3">
                                @if ($addon['status'] === 'deployed')
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-400">Deployed</span>
                                @elseif ($addon['status'] === 'failed')
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-500/20 text-red-400">Failed</span>
                                @elseif ($addon['status'] === 'pending')
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-500/20 text-gray-400">{{ $addon['status'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-forms.button type="button" wire:click="checkStatus('{{ $addon['id'] }}')" class="!px-2 !py-1 text-xs">
                                        Status
                                    </x-forms.button>
                                    <x-forms.button type="button" wire:click="deployAddon('{{ $addon['id'] }}')" class="!px-2 !py-1 text-xs" :disabled="$addon['status'] === 'deployed'">
                                        Deploy
                                    </x-forms.button>
                                    <x-forms.button type="button" wire:click="editAddon('{{ $addon['id'] }}')" class="!px-2 !py-1 text-xs">
                                        Edit
                                    </x-forms.button>
                                    <x-forms.button type="button" wire:click="deleteAddon('{{ $addon['id'] }}')" class="!px-2 !py-1 text-xs !bg-red-500/20 !text-red-400 hover:!bg-red-500/30">
                                        Delete
                                    </x-forms.button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="bg-theme-card rounded-lg border border-theme p-12 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-helper opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <h3 class="text-lg font-medium mb-2">No Addons Yet</h3>
                <p class="text-helper mb-6">Deploy databases, caches, and middleware to your K8s clusters</p>
                <x-forms.button type="button" wire:click="createAddon">
                    Create Your First Addon
                </x-forms.button>
            </div>
            @endif

            {{-- Status Result --}}
            @if ($statusResult)
                <div class="mt-4 p-3 rounded-lg {{ str_starts_with($statusResult, 'error') ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400' }}">
                    {{ $statusResult }}
                </div>
            @endif

            {{-- Deploy Result --}}
            @if ($deployResult)
                <div class="mt-4 p-3 rounded-lg {{ str_starts_with($deployResult, 'error') ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400' }}">
                    {{ $deployResult }}
                </div>
            @endif

            {{-- Form Modal --}}
            @if ($showForm)
            <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="cancelForm" wire:click.self="cancelForm">
                <div class="bg-theme-card rounded-lg border border-theme w-full max-w-2xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                    <div class="p-6 border-b border-theme">
                        <h3 class="text-lg font-medium">{{ $isEditing ? 'Edit Addon' : 'Create New Addon' }}</h3>
                    </div>

                    <form wire:submit="saveAddon" class="p-6 space-y-6">

                        {{-- Basic Info --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input label="Addon Name" wire:model="name" placeholder="my-postgres" required />
                            </div>
                            <div>
                                <x-forms.select label="Cluster" wire:model="cluster_id" required>
                                    <option value="">Select a cluster</option>
                                    @foreach ($clusters as $cluster)
                                        <option value="{{ $cluster['id'] }}">{{ $cluster['name'] }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.select label="Addon Type" wire:model="type" required>
                                    @foreach ($availableTypes as $key => $type)
                                        <option value="{{ $key }}">{{ $type['icon'] }} {{ $type['name'] }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                            <div>
                                <x-forms.input label="Namespace" wire:model="namespace" placeholder="kubero-addons" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-forms.select label="Size" wire:model="size">
                                    <option value="small">Small</option>
                                    <option value="medium">Medium</option>
                                    <option value="large">Large</option>
                                </x-forms.select>
                            </div>
                            <div>
                                <x-forms.input label="Version" wire:model="version" placeholder="latest" />
                            </div>
                            <div>
                                <x-forms.input type="number" label="Storage (GB)" wire:model="storage_gb" min="1" max="1000" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-forms.toggle field="high_availability" />
                            <div>
                                <div class="text-sm">High Availability</div>
                                <div class="text-xs text-helper">Deploy with multiple replicas</div>
                            </div>
                        </div>

                        {{-- Database fields (conditional) --}}
                        @if (!in_array($type, ['redis', 'rabbitmq', 'minio']))
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-forms.input label="Database Name" wire:model="database_name" placeholder="mydb" />
                            </div>
                            <div>
                                <x-forms.input label="Username" wire:model="username" placeholder="admin" />
                            </div>
                        </div>
                        @endif

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-theme">
                            <x-forms.button type="button" wire:click="cancelForm" class="!bg-theme-hover">
                                Cancel
                            </x-forms.button>
                            <x-forms.button type="submit" :disabled="$deploying">
                                {{ $isEditing ? 'Update Addon' : 'Create Addon' }}
                            </x-forms.button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
