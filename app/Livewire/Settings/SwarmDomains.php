<?php

namespace App\Livewire\Settings;

use App\Models\Application;
use App\Models\SwarmDomainMapping;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SwarmDomains extends Component
{
    public $domain_mappings = [];

    public ?int $editing_id = null;

    // Basic fields
    #[Validate('required')]
    public ?string $domain = null;

    #[Validate('nullable')]
    public ?string $path_prefix = '/';

    #[Validate('required')]
    public ?int $application_id = null;

    public ?int $port = 80;

    public ?string $scheme = 'http';

    public bool $is_enabled = true;

    // Rate Limiting
    public ?int $rate_limit_average = null;

    public ?int $rate_limit_burst = null;

    public ?string $rate_limit_period = '1m';

    // Security Headers
    public bool $enable_security_headers = false;

    public bool $header_xss_filter = true;

    public bool $header_content_type_nosniff = true;

    public bool $header_frame_deny = true;

    public ?int $header_sts_seconds = null;

    public bool $header_sts_include_subdomains = false;

    // IP Whitelist
    public bool $ip_whitelist_enabled = false;

    public ?string $ip_whitelist_sources = null;

    public bool $testing_connection = false;

    public ?string $test_result = null;

    public function mount()
    {
        $this->loadMappings();
    }

    public function loadMappings()
    {
        $this->domain_mappings = SwarmDomainMapping::with('application')->get()->toArray();
    }

    public function submit()
    {
        $this->validate();

        $data = [
            'domain' => $this->domain,
            'path_prefix' => $this->path_prefix ?: '/',
            'application_id' => $this->application_id,
            'port' => $this->port ?: 80,
            'scheme' => $this->scheme ?: 'http',
            'is_enabled' => $this->is_enabled,
            // Rate Limiting
            'rate_limit_average' => $this->rate_limit_average,
            'rate_limit_burst' => $this->rate_limit_burst,
            'rate_limit_period' => $this->rate_limit_period,
            // Security Headers
            'enable_security_headers' => $this->enable_security_headers,
            'header_xss_filter' => $this->header_xss_filter,
            'header_content_type_nosniff' => $this->header_content_type_nosniff,
            'header_frame_deny' => $this->header_frame_deny,
            'header_sts_seconds' => $this->header_sts_seconds,
            'header_sts_include_subdomains' => $this->header_sts_include_subdomains,
            // IP Whitelist
            'ip_whitelist_enabled' => $this->ip_whitelist_enabled,
            'ip_whitelist_sources' => $this->ip_whitelist_sources,
        ];

        if ($this->editing_id) {
            SwarmDomainMapping::find($this->editing_id)->update($data);
        } else {
            SwarmDomainMapping::create($data);
        }

        $this->resetForm();
        $this->loadMappings();
        $this->dispatch('success', 'Domain mapping saved.');
    }

    public function edit($id)
    {
        $mapping = SwarmDomainMapping::find($id);
        $this->editing_id = $id;
        $this->domain = $mapping->domain;
        $this->path_prefix = $mapping->path_prefix;
        $this->application_id = $mapping->application_id;
        $this->port = $mapping->port;
        $this->scheme = $mapping->scheme;
        $this->is_enabled = $mapping->is_enabled;
        // Rate Limiting
        $this->rate_limit_average = $mapping->rate_limit_average;
        $this->rate_limit_burst = $mapping->rate_limit_burst;
        $this->rate_limit_period = $mapping->rate_limit_period;
        // Security Headers
        $this->enable_security_headers = $mapping->enable_security_headers;
        $this->header_xss_filter = $mapping->header_xss_filter;
        $this->header_content_type_nosniff = $mapping->header_content_type_nosniff;
        $this->header_frame_deny = $mapping->header_frame_deny;
        $this->header_sts_seconds = $mapping->header_sts_seconds;
        $this->header_sts_include_subdomains = $mapping->header_sts_include_subdomains;
        // IP Whitelist
        $this->ip_whitelist_enabled = $mapping->ip_whitelist_enabled;
        $this->ip_whitelist_sources = $mapping->ip_whitelist_sources;
    }

    public function delete($id)
    {
        SwarmDomainMapping::find($id)->delete();
        $this->loadMappings();
        $this->dispatch('success', 'Domain mapping deleted.');
    }

    public function toggleEnabled($id)
    {
        $mapping = SwarmDomainMapping::find($id);
        $mapping->is_enabled = ! $mapping->is_enabled;
        $mapping->save();
        $this->loadMappings();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->domain = null;
        $this->path_prefix = '/';
        $this->application_id = null;
        $this->port = 80;
        $this->scheme = 'http';
        $this->is_enabled = true;
        // Rate Limiting
        $this->rate_limit_average = null;
        $this->rate_limit_burst = null;
        $this->rate_limit_period = '1m';
        // Security Headers
        $this->enable_security_headers = false;
        $this->header_xss_filter = true;
        $this->header_content_type_nosniff = true;
        $this->header_frame_deny = true;
        $this->header_sts_seconds = null;
        $this->header_sts_include_subdomains = false;
        // IP Whitelist
        $this->ip_whitelist_enabled = false;
        $this->ip_whitelist_sources = null;
    }

    public function getApplicationsProperty()
    {
        return Application::where('destination_type', 'App\Models\SwarmDocker')->get();
    }

    public function render()
    {
        return view('livewire.settings.swarm-domains');
    }
}