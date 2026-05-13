<?php

use App\Models\Concern;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $concernId = null;

    public string $text = '';
    public ?string $raised_by_stakeholder_id = null;
    public string $viewpoint_hints_text = '';

    #[On('edit-concern')]
    public function load(string $concernId): void
    {
        $concern = Concern::find($concernId);

        abort_if($concern === null, 404);

        $this->concernId = $concernId;
        $this->text = $concern->text;
        $this->raised_by_stakeholder_id = $concern->raised_by_stakeholder_id;
        $this->viewpoint_hints_text = is_array($concern->viewpoint_hints)
            ? implode(', ', $concern->viewpoint_hints)
            : '';

        $this->modal('edit-concern')->show();
    }

    #[Computed]
    public function concern(): ?Concern
    {
        return $this->concernId ? Concern::find($this->concernId) : null;
    }

    #[Computed]
    public function stakeholderOptions()
    {
        return $this->concern?->project->stakeholders()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $concern = $this->concern;

        abort_if($concern === null, 404);

        $data = $this->validate([
            'text' => ['required', 'string'],
            'raised_by_stakeholder_id' => [
                'nullable',
                Rule::exists('stakeholders', 'id')->where('project_id', $concern->project_id),
            ],
            'viewpoint_hints_text' => ['nullable', 'string'],
        ]);

        $concern->update([
            'text' => $data['text'],
            'raised_by_stakeholder_id' => $data['raised_by_stakeholder_id'] ?: null,
            'viewpoint_hints' => $this->splitHints($data['viewpoint_hints_text'] ?? ''),
        ]);

        $this->modal('edit-concern')->close();
        $this->dispatch('concern-saved');
    }

    /**
     * @return array<int, string>|null
     */
    private function splitHints(string $text): ?array
    {
        $hints = collect(preg_split('/[,\r\n]+/', $text))
            ->map(fn (string $hint): string => trim($hint))
            ->filter()
            ->values()
            ->all();

        return $hints === [] ? null : $hints;
    }
}; ?>

<flux:modal name="edit-concern" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit concern') }}</flux:heading>
        </div>

        <flux:textarea wire:model="text" :label="__('Text')" rows="3" required />

        <flux:select wire:model="raised_by_stakeholder_id" :label="__('Raised by')">
            <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
            @foreach ($this->stakeholderOptions as $stakeholder)
                <flux:select.option value="{{ $stakeholder->id }}">{{ $stakeholder->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="viewpoint_hints_text" :label="__('Viewpoint hints')" :placeholder="__('Comma-separated')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
