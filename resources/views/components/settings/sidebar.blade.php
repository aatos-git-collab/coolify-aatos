<div class="sub-menu-wrapper">
    <a class="sub-menu-item {{ $activeMenu === 'general' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.index') }}"><span class="menu-item-label">General</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'advanced' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.advanced') }}"><span class="menu-item-label">Advanced</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'updates' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.updates') }}"><span class="menu-item-label">Updates</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'ai' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.ai') }}"><span class="menu-item-label">AI Debug</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'ai-monitor' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.ai-monitor') }}"><span class="menu-item-label">AI Monitor</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'access-lists' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.access-lists') }}"><span class="menu-item-label">Access Lists</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'swarm-domains' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.swarm-domains') }}"><span class="menu-item-label">Swarm LB</span></a>
    <div class="sub-menu-title">Kubernetes</div>
    <a class="sub-menu-item {{ $activeMenu === 'kubernetes' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.kubernetes') }}"><span class="menu-item-label">Clusters</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'kubernetes-apps' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.kubernetes-apps') }}"><span class="menu-item-label">Apps</span></a>
    <a class="sub-menu-item {{ $activeMenu === 'kubernetes-addons' ? 'menu-item-active' : '' }}" {{ wireNavigate() }}
        href="{{ route('settings.kubernetes-addons') }}"><span class="menu-item-label">Addons</span></a>
</div>
