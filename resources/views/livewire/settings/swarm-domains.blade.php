<div>
    <x-slot:title>
        Swarm Load Balancer | Coolify
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="swarm-domains" />
        <div class="flex flex-col w-full">
            <div class="flex items-center gap-2">
                <h2>Swarm Load Balancer</h2>
            </div>
            <div class="pb-4">Configure domain routing for swarm services. Point your domain to this server's IP and routes will be managed automatically.</div>

            {{-- Domain Mapping Form --}}
            <form wire:submit='submit' class="p-4 mb-4 border rounded bg-white dark:bg-coolgray-100 border-neutral-200 dark:border-coolgray-300">
                <h3 class="mb-4 font-bold">Domain Configuration</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-forms.input id="domain" label="Domain" placeholder="api.example.com" />

                    <x-forms.input id="path_prefix" label="Path Prefix" placeholder="/" />

                    <x-forms.select id="application_id" label="Service">
                        <option value="">Select a service...</option>
                        @foreach ($this->applications as $app)
                            <option value="{{ $app->id }}">{{ $app->name }} ({{ $app->swarm_service_identifier }})</option>
                        @endforeach
                    </x-forms.select>

                    <div class="flex gap-4">
                        <x-forms.input type="number" id="port" label="Port" />

                        <x-forms.select id="scheme" label="Scheme">
                            <option value="http">HTTP</option>
                            <option value="https">HTTPS</option>
                        </x-forms.select>
                    </div>
                </div>

                <div class="flex items-center mt-4">
                    <x-forms.checkbox id="is_enabled" label="Enabled" />
                </div>

                {{-- Rate Limiting Section --}}
                <div class="mt-6 border-t border-neutral-200 dark:border-coolgray-700 pt-4">
                    <h4 class="mb-4 font-bold">Rate Limiting</h4>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <x-forms.input type="number" id="rate_limit_average" label="Average (req/sec)"
                            helper="Maximum average requests per second" />
                        <x-forms.input type="number" id="rate_limit_burst" label="Burst"
                            helper="Maximum burst above average" />
                        <x-forms.select id="rate_limit_period" label="Period">
                            <option value="1s">1 second</option>
                            <option value="1m">1 minute</option>
                            <option value="1h">1 hour</option>
                        </x-forms.select>
                    </div>
                </div>

                {{-- Security Headers Section --}}
                <div class="mt-6 border-t border-neutral-200 dark:border-coolgray-700 pt-4">
                    <h4 class="mb-4 font-bold">Security Headers</h4>
                    <div class="flex flex-col gap-2">
                        <x-forms.checkbox id="enable_security_headers" label="Enable Security Headers" />
                        @if ($enable_security_headers)
                            <div class="grid grid-cols-2 gap-4 pl-4 border-l-2 border-neutral-300 dark:border-coolgray-600">
                                <x-forms.checkbox id="header_xss_filter" label="X-XSS-Protection" />
                                <x-forms.checkbox id="header_content_type_nosniff" label="X-Content-Type-Options" />
                                <x-forms.checkbox id="header_frame_deny" label="X-Frame-Options (DENY)" />
                                <x-forms.input type="number" id="header_sts_seconds" label="HSTS Max Age (seconds)"
                                    helper="0 to disable, e.g. 31536000 for 1 year" />
                                <x-forms.checkbox id="header_sts_include_subdomains" label="HSTS Include Subdomains" />
                            </div>
                        @endif
                    </div>
                </div>

                {{-- IP Whitelist Section --}}
                <div class="mt-6 border-t border-neutral-200 dark:border-coolgray-700 pt-4">
                    <h4 class="mb-4 font-bold">IP Whitelist</h4>
                    <div class="flex flex-col gap-2">
                        <x-forms.checkbox id="ip_whitelist_enabled" label="Enable IP Whitelist" />
                        @if ($ip_whitelist_enabled)
                            <div class="pl-4 border-l-2 border-neutral-300 dark:border-coolgray-600">
                                <x-forms.textarea id="ip_whitelist_sources" label="Allowed IPs"
                                    placeholder="192.168.1.0/24, 10.0.0.0/8"
                                    helper="Comma-separated list of IP addresses or CIDR ranges" rows="2" />
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <div class="flex gap-2">
                        @if ($editing_id)
                            <x-forms.button type="button" wire:click="resetForm" class="secondary">
                                Cancel
                            </x-forms.button>
                        @endif
                        <x-forms.button type="submit">
                            {{ $editing_id ? 'Update' : 'Add' }} Mapping
                        </x-forms.button>
                    </div>
                </div>
            </form>

            {{-- Domain Mappings List --}}
            @if (count($domain_mappings) > 0)
                <div class="border rounded border-neutral-200 dark:border-coolgray-700">
                    <table class="w-full">
                        <thead class="bg-neutral-50 dark:bg-coolgray-200">
                            <tr>
                                <th class="px-4 py-2 text-left">Domain</th>
                                <th class="px-4 py-2 text-left">Service</th>
                                <th class="px-4 py-2 text-left">Middlewares</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($domain_mappings as $mapping)
                                <tr class="border-t border-neutral-200 dark:border-coolgray-700">
                                    <td class="px-4 py-2 font-mono text-sm">{{ $mapping['domain'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 bg-neutral-200 dark:bg-coolgray-600 rounded">
                                            {{ $mapping['application']['swarm_service_identifier'] ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex gap-1">
                                            @if ($mapping['rate_limit_average'])
                                                <span class="text-xs px-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded">Rate</span>
                                            @endif
                                            @if ($mapping['enable_security_headers'])
                                                <span class="text-xs px-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded">Security</span>
                                            @endif
                                            @if ($mapping['ip_whitelist_enabled'])
                                                <span class="text-xs px-1 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 rounded">IP</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <button wire:click="toggleEnabled({{ $mapping['id'] }})" class="text-xs">
                                            @if ($mapping['is_enabled'])
                                                <span class="text-success">Enabled</span>
                                            @else
                                                <span class="text-neutral-400">Disabled</span>
                                            @endif
                                        </button>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button wire:click="edit({{ $mapping['id'] }})" class="text-blue-500 hover:underline mr-2">Edit</button>
                                        <button wire:click="delete({{ $mapping['id'] }})" class="text-error hover:underline">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center text-neutral-400">
                    No domain mappings configured. Add your first mapping above.
                </div>
            @endif

            <div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/30 rounded">
                <h4 class="font-bold text-blue-400">Setup Instructions</h4>
                <ol class="mt-2 text-sm text-neutral-300 list-decimal list-inside">
                    <li>Add domain mappings above for each service you want to expose</li>
                    <li>Point your domain's A record to this server's IP address (Cloudflare)</li>
                    <li>Configure rate limiting, security headers, or IP whitelist as needed</li>
                    <li>Traefik will automatically route traffic and manage SSL</li>
                </ol>
            </div>
        </div>
    </div>
</div>