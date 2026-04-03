<?php

namespace App\Livewire\Settings;

use App\Models\AccessList;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AccessLists extends Component
{
    public $accessLists = [];

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $ips = '';

    #[Validate('nullable|string|max:500')]
    public ?string $description = '';

    public ?AccessList $editingList = null;

    public function mount()
    {
        $this->loadAccessLists();
    }

    public function loadAccessLists()
    {
        $this->accessLists = AccessList::orderBy('name')->get()->toArray();
    }

    public function create()
    {
        $this->validate();

        $ipArray = array_filter(array_map('trim', explode(',', $this->ips)));

        AccessList::create([
            'name' => $this->name,
            'ips' => $ipArray,
            'description' => $this->description,
        ]);

        $this->resetForm();
        $this->loadAccessLists();

        $this->dispatch('success', 'Access list created successfully.');
    }

    public function edit(AccessList $accessList)
    {
        $this->editingList = $accessList;
        $this->name = $accessList->name;
        $this->ips = implode(', ', $accessList->ips ?? []);
        $this->description = $accessList->description;
    }

    public function update()
    {
        if (! $this->editingList) {
            return;
        }

        $this->validate();

        $ipArray = array_filter(array_map('trim', explode(',', $this->ips)));

        $this->editingList->update([
            'name' => $this->name,
            'ips' => $ipArray,
            'description' => $this->description,
        ]);

        $this->resetForm();
        $this->loadAccessLists();

        $this->dispatch('success', 'Access list updated successfully.');
    }

    public function delete(AccessList $accessList)
    {
        // Check if it's in use
        if ($accessList->applicationSettings()->count() > 0) {
            $this->dispatch('error', 'Cannot delete: This access list is in use by an application.');

            return;
        }

        $accessList->delete();
        $this->loadAccessLists();

        $this->dispatch('success', 'Access list deleted successfully.');
    }

    public function cancelEdit()
    {
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->name = '';
        $this->ips = '';
        $this->description = '';
        $this->editingList = null;
    }

    public function render()
    {
        return view('livewire.settings.access-lists');
    }
}