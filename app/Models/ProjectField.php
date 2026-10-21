<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A custom field defined on a project, filled in on each of its tasks.
 * Values are stored as text in task_field_values:
 *   select → option id, multiselect → JSON array of option ids, person → user id,
 *   checkbox → "1", date → Y-m-d, number/currency → numeric string.
 */
class ProjectField extends Model
{
    public const TYPES = [
        'text' => 'Text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'currency' => 'Currency',
        'date' => 'Date',
        'select' => 'Dropdown',
        'multiselect' => 'Multi-select',
        'checkbox' => 'Checkbox',
        'url' => 'Link (URL)',
        'person' => 'Person',
    ];

    /** Option colors → full Tailwind class strings (kept literal so Tailwind compiles them). */
    public const COLORS = [
        'gray' => 'bg-gray-100 text-gray-700',
        'red' => 'bg-red-100 text-red-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'amber' => 'bg-amber-100 text-amber-800',
        'green' => 'bg-emerald-100 text-emerald-700',
        'teal' => 'bg-teal-100 text-teal-700',
        'blue' => 'bg-sky-100 text-sky-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
        'purple' => 'bg-violet-100 text-violet-700',
        'pink' => 'bg-pink-100 text-pink-700',
    ];

    public const SWATCHES = [
        'gray' => 'bg-gray-400', 'red' => 'bg-red-400', 'orange' => 'bg-orange-400', 'amber' => 'bg-amber-400', 'green' => 'bg-emerald-400',
        'teal' => 'bg-teal-400', 'blue' => 'bg-sky-400', 'indigo' => 'bg-indigo-400', 'purple' => 'bg-violet-400', 'pink' => 'bg-pink-400',
    ];

    /** Ready-made fields offered when setting up a project. */
    public const PRESETS = [
        'ticket_type' => ['name' => 'Type', 'type' => 'select', 'options' => ['Feature' => 'blue', 'Bug' => 'red', 'Improvement' => 'green', 'Support' => 'amber']],
        'story_points' => ['name' => 'Story points', 'type' => 'number', 'options' => []],
        'effort' => ['name' => 'Effort', 'type' => 'select', 'options' => ['Small' => 'green', 'Medium' => 'amber', 'Large' => 'red']],
        'stage' => ['name' => 'Stage', 'type' => 'select', 'options' => ['Not started' => 'gray', 'In progress' => 'blue', 'Blocked' => 'red', 'Done' => 'green']],
        'budget' => ['name' => 'Budget', 'type' => 'currency', 'options' => []],
        'platform' => ['name' => 'Platform', 'type' => 'multiselect', 'options' => ['Web' => 'blue', 'iOS' => 'gray', 'Android' => 'green']],
        'reviewer' => ['name' => 'Reviewer', 'type' => 'person', 'options' => []],
        'link' => ['name' => 'Reference link', 'type' => 'url', 'options' => []],
    ];

    protected $fillable = ['project_id', 'name', 'type', 'options', 'description', 'required', 'show_on_card', 'client_visible', 'position'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'show_on_card' => 'boolean',
            'client_visible' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(TaskFieldValue::class, 'field_id');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, ['select', 'multiselect'], true);
    }

    /** @return array<int, array{id: string, label: string, color: string}> */
    public function optionList(): array
    {
        return array_values(array_filter($this->options ?? [], fn ($o) => is_array($o) && isset($o['id'], $o['label'])));
    }

    public function option(?string $id): ?array
    {
        foreach ($this->optionList() as $o) {
            if ($o['id'] === $id) {
                return $o;
            }
        }

        return null;
    }

    /** Build an options array from labels (or label => color). */
    public static function makeOptions(array $labels): array
    {
        $colors = array_keys(self::COLORS);
        $out = [];
        $i = 1;
        foreach ($labels as $label => $color) {
            if (is_int($label)) {
                [$label, $color] = [$color, $colors[$i % count($colors)]];
            }
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $out[] = ['id' => Str::lower(Str::random(8)), 'label' => mb_substr($label, 0, 60), 'color' => array_key_exists($color, self::COLORS) ? $color : 'gray'];
            $i++;
        }

        return $out;
    }

    /** Validation rules for one value of this field. */
    public function rules(): array
    {
        $base = $this->required ? ['required'] : ['nullable'];

        return match ($this->type) {
            'number' => [...$base, 'numeric', 'between:-999999999,999999999'],
            'currency' => [...$base, 'numeric', 'between:-999999999,999999999'],
            'date' => [...$base, 'date'],
            'url' => [...$base, 'url:http,https', 'max:500'],
            'select' => [...$base, 'in:'.implode(',', array_column($this->optionList(), 'id'))],
            'multiselect' => [...($this->required ? ['required'] : ['nullable']), 'array'],
            'checkbox' => ['nullable', 'boolean', ...($this->required ? ['accepted'] : [])],
            // Only someone who can open this project (not a client or an id from elsewhere).
            'person' => [...$base, 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! Project::find($this->project_id)?->canBeAssigned((int) $value)) {
                    $fail('Pick someone who can open this project.');
                }
            }],
            'textarea' => [...$base, 'string', 'max:5000'],
            default => [...$base, 'string', 'max:500'],
        };
    }

    /** Turn form input into the stored string (or null when empty). */
    public function normalize(mixed $input): ?string
    {
        if ($this->type === 'multiselect') {
            $valid = array_column($this->optionList(), 'id');
            $ids = array_values(array_intersect($valid, (array) $input));

            return $ids ? json_encode($ids) : null;
        }
        if ($this->type === 'checkbox') {
            return filter_var($input, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
        }
        if (is_array($input)) {
            return null;
        }

        $value = trim((string) $input);
        if ($value === '') {
            return null;
        }

        return match ($this->type) {
            'number', 'currency' => is_numeric($value) ? (string) (0 + $value) : null,
            'date' => strtotime($value) ? date('Y-m-d', strtotime($value)) : null,
            'person' => ctype_digit($value) ? $value : null,
            default => $value,
        };
    }

    /** Stored string → what the form input expects. */
    public function toInput(?string $stored): mixed
    {
        return match ($this->type) {
            'multiselect' => $stored ? (json_decode($stored, true) ?: []) : [],
            'checkbox' => $stored === '1',
            default => $stored ?? '',
        };
    }

    /** Human-readable value. $people: id => name (for person fields). */
    public function display(?string $stored, array $people = []): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return match ($this->type) {
            'select' => $this->option($stored)['label'] ?? null,
            'multiselect' => implode(', ', array_filter(array_map(fn ($id) => $this->option($id)['label'] ?? null, json_decode($stored, true) ?: []))) ?: null,
            'checkbox' => $stored === '1' ? 'Yes' : null,
            'date' => date('M j, Y', strtotime($stored)),
            'currency' => number_format((float) $stored, 2),
            'number' => rtrim(rtrim(number_format((float) $stored, 2, '.', ','), '0'), '.'),
            'person' => $people[(int) $stored] ?? 'Former member',
            default => $stored,
        };
    }
}
