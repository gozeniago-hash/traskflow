<?php

namespace Tests\Feature\Actions\Comments;

use App\Actions\Comments\AddComment;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Posting a comment with files. */
class AddCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_the_comment_with_its_files(): void
    {
        Storage::fake('local');
        $task = Task::factory()->create();
        $user = User::factory()->create();

        $comment = app(AddComment::class)->handle($task, $user, ' Here is the brief ', true, [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]);

        $this->assertSame('Here is the brief', $comment->body);
        $this->assertTrue($comment->is_internal);
        $this->assertSame(['brief.pdf'], Attachment::where('comment_id', $comment->id)->pluck('original_name')->all());
    }

    public function test_a_file_that_does_not_fit_cancels_the_whole_comment(): void
    {
        Storage::fake('local');
        config(['taskflow.workspace_storage_mb' => 1]);
        $task = Task::factory()->create();

        try {
            app(AddComment::class)->handle($task, User::factory()->create(), 'Big file', false, [UploadedFile::fake()->create('video.pdf', 2048, 'application/pdf')]);
            $this->fail('The comment was posted although its file does not fit.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Comment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
