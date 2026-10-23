<?php

namespace Tests\Feature\Enums;

use App\Enums\ApprovalStatus;
use App\Enums\Concerns\EnumHelpers;
use Tests\TestCase;

/** The shared helpers every enum gets from EnumHelpers. */
class EnumHelpersTest extends TestCase
{
    public function test_label_defaults_to_the_case_name_as_a_headline(): void
    {
        $this->assertSame('In Progress', SampleStatus::InProgress->label());
    }

    public function test_an_enum_can_write_its_own_labels(): void
    {
        $this->assertSame('Awaiting approval', ApprovalStatus::Pending->label());
    }

    public function test_options_list_every_case_except_the_ones_left_out(): void
    {
        $this->assertSame([
            ['id' => 'pending', 'name' => 'Awaiting approval', 'value' => 'pending', 'label' => 'Awaiting approval'],
            ['id' => 'changes_requested', 'name' => 'Changes requested', 'value' => 'changes_requested', 'label' => 'Changes requested'],
        ], ApprovalStatus::options(except: ['approved']));
    }

    public function test_dropdown_data_maps_values_to_labels(): void
    {
        $this->assertSame(['todo' => 'Todo', 'in_progress' => 'In Progress'], SampleStatus::getSelectDropdownData());
    }

    public function test_random_returns_one_of_the_cases(): void
    {
        $this->assertContains(SampleStatus::random(), SampleStatus::cases());
    }
}

enum SampleStatus: string
{
    use EnumHelpers;

    case Todo = 'todo';
    case InProgress = 'in_progress';
}
