<div>
    <x-slot:title>
        AI Monitor | Coolify
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="ai-monitor" />
        <form wire:submit='submit' class="flex flex-col w-full">
            <div class="flex items-center gap-2">
                <h2>AI Monitor (Self-Healing)</h2>
                <x-forms.button type="submit">
                    Save
                </x-forms.button>
            </div>
            <div class="pb-4">Configure AI-powered container monitoring and auto-healing.</div>

            <div class="p-4 mb-4 border rounded bg-white dark:bg-coolgray-100 border-neutral-200 dark:border-coolgray-300">
                <h3 class="mb-4 font-bold">Monitoring Settings</h3>

                <div class="flex flex-col gap-4">
                    <x-forms.checkbox id="ai_monitor_enabled" label="Enable AI Monitor"
                        helper="Continuously monitor container logs for errors and issues." />

                    @if ($ai_monitor_enabled)
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 pl-4 border-l-2 border-neutral-300 dark:border-coolgray-600">
                            <x-forms.input type="number" id="ai_monitor_interval" label="Check Interval (minutes)"
                                helper="How often to check container logs." />

                            <x-forms.input type="number" id="ai_monitor_log_lines" label="Log Lines to Analyze"
                                helper="Number of log lines to fetch for analysis." />
                        </div>
                    @endif
                </div>
            </div>

            <div class="p-4 mb-4 border rounded bg-white dark:bg-coolgray-100 border-neutral-200 dark:border-coolgray-300">
                <h3 class="mb-4 font-bold">Auto-Healing</h3>

                <div class="flex flex-col gap-4">
                    <x-forms.checkbox id="ai_auto_heal_enabled" label="Enable Auto-Heal"
                        helper="Automatically attempt to fix detected issues (e.g., restart failed containers)." />

                    @if ($ai_auto_heal_enabled)
                        <div class="p-2 text-sm text-warning">
                            Warning: Auto-healing will automatically restart containers and attempt fixes based on AI analysis.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Healing History --}}
            @if (count($healing_logs) > 0)
                <div class="border rounded border-neutral-200 dark:border-coolgray-700">
                    <table class="w-full">
                        <thead class="bg-neutral-50 dark:bg-coolgray-200">
                            <tr>
                                <th class="px-4 py-2 text-left">Time</th>
                                <th class="px-4 py-2 text-left">Container</th>
                                <th class="px-4 py-2 text-left">Issue</th>
                                <th class="px-4 py-2 text-left">Action</th>
                                <th class="px-4 py-2 text-left">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($healing_logs as $log)
                                <tr class="border-t border-neutral-200 dark:border-coolgray-700">
                                    <td class="px-4 py-2 text-xs">{{ $log['created_at'] }}</td>
                                    <td class="px-4 py-2 text-xs font-mono">{{ $log['container_name'] }}</td>
                                    <td class="px-4 py-2 text-xs">{{ substr($log['issue_detected'] ?? '', 0, 50) }}...</td>
                                    <td class="px-4 py-2 text-xs">{{ $log['remediation_tried'] ?? 'Analyzed' }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @if ($log['result'] === 'success')
                                            <span class="text-success">Healed</span>
                                        @elseif ($log['result'] === 'failed')
                                            <span class="text-error">Failed</span>
                                        @else
                                            <span class="text-neutral-400">Analyzed</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </form>
    </div>
</div>
