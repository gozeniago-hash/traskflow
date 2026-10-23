<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TaskReopenController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UndoController;
use App\Http\Controllers\WorkspaceController;
use App\Livewire;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

// ----------------------------------------------------------------- guests
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'store'])->middleware('throttle:login');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

// Invitations work whether or not someone is signed in.
// GitHub calls this; it's authenticated by the per-repository webhook signature, not a session.
Route::post('/github/webhook/{repository}', GitHubWebhookController::class)
    ->middleware('throttle:120,1')->name('github.webhook');

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:invitation-accept')->name('invitations.accept');

// ---------------------------------------------------------- signed-in users
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');

    Route::middleware(['secure', 'workspace'])->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('/t/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/undo/{token}', UndoController::class)->name('undo');
        Route::post('/tasks/{task}/reopen', TaskReopenController::class)->whereNumber('task')->name('tasks.reopen');

        // ------------------------------------------------- internal team app
        Route::middleware('member')->group(function (): void {
            Route::livewire('/home', Livewire\Home::class)->name('home');
            Route::livewire('/my-tasks', Livewire\MyTasks::class)->name('my-tasks');
            Route::livewire('/search', Livewire\Search::class)->name('search');
            Route::livewire('/profile', Livewire\Profile::class)->name('profile');
            Route::livewire('/notifications', Livewire\Notifications\Index::class)->name('notifications');

            Route::livewire('/projects', Livewire\Projects\Index::class)->name('projects.index');
            Route::livewire('/projects/{project}/{view?}', Livewire\Projects\Show::class)
                ->whereIn('view', array_keys(Project::VIEWS))
                ->name('projects.show');

            Route::livewire('/reports', Livewire\Reports\Index::class)->name('reports');

            Route::middleware('admin')->group(function (): void {
                Route::livewire('/settings', Livewire\Admin\Settings::class)->name('settings');
                // Workspace modules are admin-only.
                Route::livewire('/teams', Livewire\Admin\Teams::class)->name('teams');
                Route::livewire('/members', Livewire\Admin\Members::class)->name('members');
                Route::livewire('/clients', Livewire\Admin\Clients::class)->name('clients');
                Route::livewire('/audit-log', Livewire\Admin\AuditLog::class)->name('audit-log');
            });

            Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        });

        // ------------------------------------------------------ client portal
        Route::middleware('client')->prefix('portal')->name('portal.')->group(function (): void {
            Route::livewire('/', Livewire\Portal\Dashboard::class)->name('dashboard');
            Route::livewire('/projects/{project}', Livewire\Portal\ProjectView::class)->name('projects.show');
            Route::livewire('/account', Livewire\Profile::class)->name('account');
            Route::livewire('/notifications', Livewire\Notifications\Index::class)->name('notifications');
        });
    });
});
