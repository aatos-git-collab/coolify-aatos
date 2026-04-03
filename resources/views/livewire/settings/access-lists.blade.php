<div>
    <x-slot:title>
        Access Lists | Coolify
    </x-slot:title>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="access-lists" />
        <div class="flex flex-col w-full">
            <div class="flex items-center gap-2 mb-4">
                <h2>Access Lists (IP Whitelist)</h2>
            </div>
            <div class="pb-4">Configure IP addresses or CIDR blocks that can access your private applications. These lists can be assigned to applications in the app settings.</div>

            <div class="p-4 mb-6 border rounded bg-white dark:bg-coolgray-100 border-neutral-200 dark:border-coolgray-300">
                <h3 class="mb-4 font-bold">{{ $editingList ? 'Edit Access List' : 'Create New Access List' }}</h3>

                <div class="flex flex-col gap-4">
                    <x-forms.input label="Name" wire:model="name" placeholder="e.g., Office IPs, Home Only" required />

                    <x-forms.input label="IP Addresses" wire:model="ips" placeholder="e.g., 192.168.1.1, 10.0.0.0/24, 172.16.0.0/16"
                        helper="Comma-separated list of IP addresses or CIDR blocks" required />

                    <x-forms.input label="Description (optional)" wire:model="description" placeholder="Optional description" />

                    <div class="flex gap-2">
                        @if ($editingList)
                            <x-forms.button wire:click="update" class="btn-primary">
                                Update
                            </x-forms.button>
                            <x-forms.button wire:click="cancelEdit" class="btn-secondary">
                                Cancel
                            </x-forms.button>
                        @else
                            <x-forms.button wire:click="create" class="btn-primary">
                                Create
                            </x-forms.button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border rounded border-neutral-200 dark:border-coolgray-300">
                <table class="w-full">
                    <thead class="bg-neutral-100 dark:bg-coolgray-200">
                        <tr>
                            <th class="p-3 text-left">Name</th>
                            <th class="p-3 text-left">IP Addresses</th>
                            <th class="p-3 text-left">Description</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accessLists as $list)
                            <tr class="border-t border-neutral-200 dark:border-coolgray-300">
                                <td class="p-3 font-medium">{{ $list['name'] }}</td>
                                <td class="p-3 font-mono text-sm">{{ implode(', ', $list['ips'] ?? []) }}</td>
                                <td class="p-3 text-neutral-500">{{ $list['description'] ?? '-' }}</td>
                                <td class="p-3">
                                    <div class="flex gap-2">
                                        <x-forms.button wire:click="edit({{ $list['id'] }})" class="btn-xs">
                                            Edit
                                        </x-forms.button>
                                        <x-forms.button wire:click="delete({{ $list['id'] }})" class="btn-xs btn-danger">
                                            Delete
                                        </x-forms.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-neutral-500">
                                    No access lists configured yet. Create one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>