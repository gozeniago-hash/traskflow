<?php

namespace Tests\Feature\Actions\Fields;

use App\Actions\Fields\SaveField;
use App\Models\Project;
use App\Models\ProjectField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Adding and changing a project's custom fields. */
class SaveFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_field_goes_at_the_end(): void
    {
        $project = Project::factory()->create();
        ProjectField::factory()->for($project)->create(['position' => 3]);

        $field = app(SaveField::class)->handle($project, null, $this->attributes('Budget'));

        $this->assertSame(4, $field->position);
    }

    public function test_a_name_another_field_already_has_is_refused_whatever_its_case(): void
    {
        $project = Project::factory()->create();
        ProjectField::factory()->for($project)->create(['name' => 'Budget']);

        try {
            app(SaveField::class)->handle($project, null, $this->attributes('budget'));
            $this->fail('A second field named Budget was added.');
        } catch (ValidationException $e) {
            $this->assertSame(['This project already has a field with that name.'], $e->errors()['form.name']);
        }

        $this->assertSame(1, $project->fields()->count());
    }

    public function test_a_field_keeps_its_own_name_when_edited(): void
    {
        $field = ProjectField::factory()->create(['name' => 'Budget']);

        app(SaveField::class)->handle($field->project, $field, ['description' => 'In euros'] + $this->attributes('Budget'));

        $this->assertSame('In euros', $field->fresh()->description);
    }

    /**
     * @return array{name: string, type: string, description: ?string, required: bool, show_on_card: bool, client_visible: bool, options: null}
     */
    private function attributes(string $name): array
    {
        return ['name' => $name, 'type' => 'text', 'description' => null, 'required' => false, 'show_on_card' => true, 'client_visible' => false, 'options' => null];
    }
}
