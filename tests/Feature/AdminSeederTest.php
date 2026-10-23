<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The first admin comes from the ADMIN_* settings and is only created once. */
class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin' => ['name' => 'Pat Owner', 'email' => 'pat@example.com', 'password' => 'secret-pass-123', 'workspace' => 'Pat Studio']]);
    }

    public function test_it_creates_a_verified_workspace_owner(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'pat@example.com')->firstOrFail();
        $this->assertTrue($admin->isOwner());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertSame('Pat Studio', $admin->currentWorkspace->name);
        $this->assertTrue($admin->currentWorkspace->teams()->where('name', 'General')->first()->users()->whereKey($admin->id)->exists());
        $this->assertCredentials(['email' => 'pat@example.com', 'password' => 'secret-pass-123']);
    }

    public function test_it_skips_when_user_one_already_exists(): void
    {
        User::factory()->create(['id' => 1]);

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'pat@example.com']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_skips_when_credentials_are_missing(): void
    {
        config(['app.admin.password' => null]);

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
