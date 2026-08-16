<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubAuthController extends Controller
{
    /**
     * Begin a GitHub OAuth handshake.
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        if (! $this->configured()) {
            return $this->unconfigured($request);
        }

        return Socialite::driver('github')->redirect();
    }

    /**
     * Handle GitHub's callback and log the user in (creating their account).
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->unconfigured($request);
        }

        try {
            $github = Socialite::driver('github')->user();
        } catch (\Throwable $exception) {
            report($exception);

            return Redirect::route('home')
                ->withErrors(['github' => 'Signing in with GitHub failed. Please try again.']);
        }

        $user = $this->firstOrCreateUser($github);

        Auth::login($user, remember: true);

        return Redirect::route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('home');
    }

    private function firstOrCreateUser(SocialiteUser $github): User
    {
        return User::query()->updateOrCreate(
            ['github_id' => $github->getId()],
            [
                'name' => $github->getName() ?? $github->getNickname() ?? $github->getEmail() ?? 'GitHub user',
                'email' => $github->getEmail(),
                'github_username' => (string) $github->getNickname(),
                'github_url' => $this->githubProfileUrl($github->getNickname()),
                'avatar_url' => $github->getAvatar(),
                'is_admin' => $this->isConfiguredAdmin($github->getNickname()),
            ],
        );
    }

    private function githubProfileUrl(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return null;
        }

        return "https://github.com/{$username}";
    }

    private function isConfiguredAdmin(?string $username): bool
    {
        if ($username === null || $username === '') {
            return false;
        }

        $admins = config('services.github.admins', []);

        return in_array(strtolower($username), array_map('strtolower', $admins), true);
    }

    private function configured(): bool
    {
        return filled(config('services.github.client_id'))
            && filled(config('services.github.client_secret'));
    }

    private function unconfigured(Request $request): RedirectResponse
    {
        return Redirect::route('home')->with(
            'status',
            'github_auth_unconfigured',
        );
    }
}
