<div>
    <x-slot:title>
        AI Smart Panel | Aatos
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="ai" />
        <form wire:submit='submit' class="flex flex-col w-full gap-8">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h2>AI Smart Panel</h2>
                    <p class="text-helper">Unified AI-powered deployment intelligence</p>
                </div>
                <x-forms.button type="submit">
                    Save All Settings
                </x-forms.button>
            </div>

            {{-- AI Provider Section --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-theme rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3>AI Provider</h3>
                        <p class="text-xs text-helper">Configure your AI service provider</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-forms.select id="ai_provider" label="Provider">
                            <option value="minimax" {{ $ai_provider === 'minimax' ? 'selected' : '' }}>MiniMax</option>
                            <option value="anthropic" {{ $ai_provider === 'anthropic' ? 'selected' : '' }}>Anthropic (Claude)</option>
                            <option value="openai" {{ $ai_provider === 'openai' ? 'selected' : '' }}>OpenAI</option>
                        </x-forms.select>
                    </div>
                    <div>
                        <x-forms.input type="password" id="ai_api_key" label="API Key" placeholder="sk-..." />
                    </div>
                    <div>
                        <x-forms.select id="ai_model" label="Model">
                            @if ($ai_provider === 'openai')
                                <option value="gpt-4o" {{ $ai_model === 'gpt-4o' ? 'selected' : '' }}>GPT-4o</option>
                                <option value="gpt-4-turbo" {{ $ai_model === 'gpt-4-turbo' ? 'selected' : '' }}>GPT-4 Turbo</option>
                            @elseif ($ai_provider === 'anthropic')
                                <option value="claude-sonnet-4-20250514" {{ $ai_model === 'claude-sonnet-4-20250514' ? 'selected' : '' }}>Claude Sonnet 4</option>
                                <option value="claude-3-5-sonnet-20240620" {{ $ai_model === 'claude-3-5-sonnet-20240620' ? 'selected' : '' }}>Claude 3.5 Sonnet</option>
                            @else
                                <option value="MiniMax-M2.7" {{ $ai_model === 'MiniMax-M2.7' ? 'selected' : '' }}>MiniMax-M2.7</option>
                            @endif
                        </x-forms.select>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-4">
                    <x-forms.button type="button" wire:click="testConnection" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('Test Connection') }}</span>
                        <span wire:loading>Testing...</span>
                    </x-forms.button>
                    @if ($test_result)
                        @if (str_starts_with($test_result, 'success:'))
                            <span class="text-success text-sm">{{ str_replace('success:', '', $test_result) }}</span>
                        @else
                            <span class="text-error text-sm">{{ str_replace('error:', '', $test_result) }}</span>
                        @endif
                    @endif
                </div>
            </div>

            {{-- AI Build Pack Section --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <div>
                            <h3>AI Build Pack</h3>
                            <p class="text-xs text-helper">Auto-detect framework & existing Docker</p>
                        </div>
                    </div>
                    <x-forms.toggle field="ai_buildpack_enabled" />
                </div>

                @if ($ai_buildpack_enabled)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-13">
                    <div class="flex items-center gap-2">
                        <x-forms.toggle field="ai_buildpack_auto_detect_docker" />
                        <div>
                            <div class="text-sm">Auto-detect Docker</div>
                            <div class="text-xs text-helper">Detect existing Dockerfile/docker-compose</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-forms.toggle field="ai_buildpack_fallback_nixpacks" />
                        <div>
                            <div class="text-sm">Fallback to Nixpacks</div>
                            <div class="text-xs text-helper">Use nixpacks if no Docker found</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- AI Auto-Fix Section --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <div>
                            <h3>AI Auto-Fix</h3>
                            <p class="text-xs text-helper">Automatic deployment failure repair</p>
                        </div>
                    </div>
                    <x-forms.toggle field="ai_autofix_enabled" />
                </div>

                @if ($ai_autofix_enabled)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pl-13">
                    <div>
                        <x-forms.input type="number" id="ai_autofix_max_retries" label="Max Retries" />
                    </div>
                    <div>
                        <x-forms.input type="number" id="ai_autofix_retry_delay" label="Retry Delay (seconds)" />
                    </div>
                    <div class="flex items-end">
                        <x-forms.button type="button" wire:click="testAiFix" wire:loading.attr="disabled">
                            <span wire:loading.remove>Test Auto-Fix</span>
                            <span wire:loading>Testing...</span>
                        </x-forms.button>
                    </div>
                </div>
                @if ($test_fix_result)
                    <div class="mt-3 p-3 bg-theme rounded text-sm">
                        @if (str_starts_with($test_fix_result, 'success:'))
                            <span class="text-success">{{ str_replace('success:', '', $test_fix_result) }}</span>
                        @else
                            <span class="text-error">{{ str_replace('error:', '', $test_fix_result) }}</span>
                        @endif
                    </div>
                @endif
                @endif
            </div>

            {{-- AI Log Monitor Section --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3>AI Log Monitor</h3>
                            <p class="text-xs text-helper">Continuous container health monitoring</p>
                        </div>
                    </div>
                    <x-forms.toggle field="ai_monitor_enabled" />
                </div>

                @if ($ai_monitor_enabled)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pl-13">
                    <div>
                        <x-forms.input type="number" id="ai_monitor_interval" label="Check Interval (seconds)" />
                    </div>
                    <div>
                        <x-forms.input type="number" id="ai_monitor_log_lines" label="Log Lines to Analyze" />
                    </div>
                    <div class="flex items-end gap-2">
                        <x-forms.button type="button" wire:click="runHealthCheck" wire:loading.attr="disabled">
                            <span wire:loading.remove>Run Health Check</span>
                            <span wire:loading>Running...</span>
                        </x-forms.button>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-4 pl-13">
                    <div class="flex items-center gap-2">
                        <x-forms.toggle field="ai_auto_heal_enabled" />
                        <div>
                            <div class="text-sm">Auto-Heal</div>
                            <div class="text-xs text-helper">Automatically restart unhealthy containers</div>
                        </div>
                    </div>
                </div>

                @if ($health_check_result)
                    <div class="mt-3 p-3 bg-theme rounded text-sm">
                        @if (str_starts_with($health_check_result, 'success:'))
                            <span class="text-success">{{ str_replace('success:', '', $health_check_result) }}</span>
                        @else
                            <span class="text-error">{{ str_replace('error:', '', $health_check_result) }}</span>
                        @endif
                    </div>
                @endif
                @endif
            </div>

            {{-- Load Balancer / Domain / IP Section --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Load Balancer & Security</h3>
                        <p class="text-xs text-helper">Domain mapping, IP whitelist, SSL management</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center gap-2">
                        <x-forms.toggle field="ip_whitelist_enabled" />
                        <div>
                            <div class="text-sm">IP Whitelist</div>
                            <div class="text-xs text-helper">Restrict access by IP range</div>
                        </div>
                    </div>
                    @if ($ip_whitelist_enabled)
                    <div>
                        <x-forms.input id="ip_whitelist_sources" label="Allowed IP Ranges" placeholder="192.168.1.0/24, 10.0.0.0/8" />
                    </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t border-theme">
                    <a href="{{ route('settings', ['section' => 'swarm-domains']) }}" class="text-sm text-link hover:underline">
                        Manage Domain Mappings →
                    </a>
                </div>
            </div>

            {{-- Recent Healing Logs --}}
            <div class="p-6 bg-theme-card rounded-lg border border-theme">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <h3>Healing Logs</h3>
                            <p class="text-xs text-helper">Recent AI auto-healing actions</p>
                        </div>
                    </div>
                    <x-forms.button type="button" wire:click="clearHealingLogs" class="!px-3 !py-1 text-xs">
                        Clear Logs
                    </x-forms.button>
                </div>

                @if (count($recentHealingLogs) > 0)
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach ($recentHealingLogs as $log)
                    <div class="p-3 bg-theme rounded text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs">{{ $log['container_name'] ?? 'unknown' }}</span>
                            <span class="text-xs text-helper">{{ $log['created_at'] ?? '' }}</span>
                        </div>
                        <div class="mt-1 text-xs">
                            @if (($log['result'] ?? '') === 'success')
                                <span class="text-success">✓ Healed</span>
                            @elseif (($log['result'] ?? '') === 'analyzed')
                                <span class="text-warning">⚠ Analyzed</span>
                            @else
                                <span class="text-error">✗ Failed</span>
                            @endif
                            - {{ Str::limit($log['issue_detected'] ?? 'No issue detected', 80) }}
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8 text-helper">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>No healing logs yet</p>
                    <p class="text-xs">Enable AI Monitor to start tracking</p>
                </div>
                @endif
            </div>

            {{-- Quick Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-theme-card rounded-lg border border-theme text-center">
                    <div class="text-2xl font-bold text-green-400">{{ $ai_autofix_enabled ? 'Active' : 'Off' }}</div>
                    <div class="text-xs text-helper">Auto-Fix</div>
                </div>
                <div class="p-4 bg-theme-card rounded-lg border border-theme text-center">
                    <div class="text-2xl font-bold text-blue-400">{{ $ai_monitor_enabled ? 'Active' : 'Off' }}</div>
                    <div class="text-xs text-helper">Log Monitor</div>
                </div>
                <div class="p-4 bg-theme-card rounded-lg border border-theme text-center">
                    <div class="text-2xl font-bold text-purple-400">{{ $ai_buildpack_enabled ? 'Active' : 'Off' }}</div>
                    <div class="text-xs text-helper">Build Pack</div>
                </div>
                <div class="p-4 bg-theme-card rounded-lg border border-theme text-center">
                    <div class="text-2xl font-bold text-orange-400">{{ $ai_auto_heal_enabled ? 'On' : 'Off' }}</div>
                    <div class="text-xs text-helper">Auto-Heal</div>
                </div>
            </div>

        </form>
    </div>
</div>
