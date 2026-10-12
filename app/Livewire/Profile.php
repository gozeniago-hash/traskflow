<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile')]
class Profile extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';
    public string $email = '';
    public string $title = '';
    public string $avatar_color = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $me = $this->me();
        $this->name = $me->name;
        $this->email = $me->email;
        $this->title = (string) $me->title;
        $this->avatar_color = (string) $me->avatar_color;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->me()->id)],
            'title' => ['nullable', 'string', 'max:120'],
            'avatar_color' => ['required', Rule::in(User::AVATAR_COLORS)],
        ]);
        $this->me()->update($data);
        $this->toast('Profile saved.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $this->me()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->toast('Password updated.');
    }

    public function render()
    {
        return view('livewire.profile', ['colors' => User::AVATAR_COLORS])
            ->layout($this->me()->isClient() ? 'layouts.portal' : 'layouts.app');
    }
}
