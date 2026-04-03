<div>
    <form wire:submit='submit' class="flex flex-col">
        <div class="flex items-center gap-2">
            <h2>Swarm Configuration</h2>
            @can('update', $application)
                <x-forms.button type="submit">
                    Save
                </x-forms.button>
            @else
                <x-forms.button type="submit" disabled
                    title="You don't have permission to update this application. Contact your team administrator for access.">
                    Save
                </x-forms.button>
            @endcan
        </div>
        <div class="flex flex-col gap-2 py-4">
            <div class="flex flex-col items-end gap-2 xl:flex-row">
                <x-forms.input id="swarmReplicas" label="Replicas" required canGate="update" :canResource="$application" />
                <x-forms.checkbox instantSave helper="If turned off, this resource will start on manager nodes too."
                    id="isSwarmOnlyWorkerNodes" label="Only Start on Worker nodes" canGate="update" :canResource="$application" />
            </div>
            <x-forms.textarea id="swarmPlacementConstraints" rows="7" label="Custom Placement Constraints"
                placeholder="placement:
    constraints:
        - 'node.role == worker'" canGate="update" :canResource="$application" />
        <x-forms.input id="swarmServiceIdentifier" label="Service Identifier (for Load Balancer)"
                helper="Unique identifier used by Swarm LB for routing. Use alphanumeric and hyphens only."
                placeholder="my-api-service" canGate="update" :canResource="$application" />
        </div>

        <div class="border-t border-coolgray-300 dark:border-coolgray-700 my-4"></div>

        <div class="flex items-center gap-2">
            <h3>Auto-Scaling</h3>
        </div>
        <div class="flex flex-col gap-2 py-4">
            <x-forms.checkbox instantSave id="autoScalingEnabled" label="Enable Auto-Scaling"
                helper="Automatically scale replicas based on CPU and memory usage." canGate="update" :canResource="$application" />

            @if ($autoScalingEnabled)
                <div class="grid grid-cols-2 gap-4">
                    <x-forms.input type="number" id="autoScalingMinReplicas" label="Min Replicas"
                        helper="Minimum number of replicas to maintain." canGate="update" :canResource="$application" />
                    <x-forms.input type="number" id="autoScalingMaxReplicas" label="Max Replicas"
                        helper="Maximum number of replicas to scale up to." canGate="update" :canResource="$application" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-forms.input type="number" id="autoScalingTargetCpu" label="Target CPU %"
                        helper="Scale up when CPU usage exceeds this percentage." canGate="update" :canResource="$application" />
                    <x-forms.input type="number" id="autoScalingTargetMemory" label="Target Memory %"
                        helper="Scale up when memory usage exceeds this percentage." canGate="update" :canResource="$application" />
                </div>
            @endif
        </div>
    </form>

</div>
