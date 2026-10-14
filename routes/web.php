<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkspaceController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

// ----------------------------------------------------------------- guests
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

// Invitations work whether or not someone is signed in.
// GitHub calls this; it's authenticated by the per-repository webhook signature, not a session.
Route::post('/github/webhook/{repository}', \App\Http\Controllers\GitHubWebhookController::class)
    ->middleware('throttle:120,1')->name('github.webhook');

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

// ---------------------------------------------------------- signed-in users
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('workspace')->group(function () {
        Route::get('/', [WorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('/t/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

        // ------------------------------------------------- internal team app
        Route::middleware('member')->group(function () {
            Route::livewire('/home', Livewire\Home::class)->name('home');
            Route::livewire('/my-tasks', Livewire\MyTasks::class)->name('my-tasks');
            Route::livewire('/search', Livewire\Search::class)->name('search');
            Route::livewire('/profile', Livewire\Profile::class)->name('profile');

            Route::livewire('/projects', Livewire\Projects\Index::class)->name('projects.index');
            Route::livewire('/projects/{project}/{view?}', Livewire\Projects\Show::class)
                ->whereIn('view', array_keys(\App\Models\Project::VIEWS))
                ->name('projects.show');

            Route::livewire('/reports', Livewire\Reports\Index::class)->name('reports');
            Route::livewire('/teams', Livewire\Admin\Teams::class)->name('teams');
            Route::livewire('/members', Livewire\Admin\Members::class)->name('members');

            Route::middleware('admin')->group(function () {
                Route::livewire('/settings', Livewire\Admin\Settings::class)->name('settings');
                // Client contact details and portal access are admin-only.
                Route::livewire('/clients', Livewire\Admin\Clients::class)->name('clients');
            });

            Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        });

        // ------------------------------------------------------ client portal
        Route::middleware('client')->prefix('portal')->name('portal.')->group(function () {
            Route::livewire('/', Livewire\Portal\Dashboard::class)->name('dashboard');
            Route::livewire('/projects/{project}', Livewire\Portal\ProjectView::class)->name('projects.show');
            Route::livewire('/account', Livewire\Profile::class)->name('account');
        });
    });
});
