<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Forms;

use Illuminate\Database\Eloquent\Model;
use Livewire\Form;

final class TaxonomyItemForm extends Form
{
    public string $name = '';

    public ?string $description = null;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Obavezno polje'),
        ];
    }

    public function fillFromModel(Model $model): void
    {
        $this->name = (string) $model->getAttribute('name');
        $this->description = $model->getAttribute('description');
    }

    /**
     * @return array{name: string, description: string|null}
     */
    public function data(): array
    {
        /** @var array{name: string, description?: string|null} $validated */
        $validated = $this->validate();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
    }

    public function resetForm(): void
    {
        $this->reset('name', 'description');
        $this->resetValidation();
    }
}
