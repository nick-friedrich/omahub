<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GitHubAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Provide OAuth credentials so the guard-route reaches Socialite.
        // The fake driver ignores the real values; the unconfigured test
        // explicitly nulls them out.
        config()->set('services.github.client_id', 'test-client-id');
        config()->set('services.github.client_secret', 'test-client-secret');
        config()->set('services.github.admins', []);
    }

    public function test_guest_sees_a_sign_in_prompt_instead_of_the_submit_form(): void
    {
        $this->get(route('submit'))
            ->assertOk()
            ->assertSee('Sign in to submit a plugin')
            ->assertSee(route('auth.github.redirect'), escape: false)
            ->assertDontSee('name="repository_url"', escape: false);
    }

    public function test_guest_is_redirected_to_login_when_posting_a_submission(): void
    {
        $this->post(route('submit.store'), ['repository_url' => 'https://github.com/acme/test'])
            ->assertRedirect('/auth/github/redirect');

        $this->assertDatabaseCount('plugin_submissions', 0);
    }

    public function test_redirect_when_github_is_unconfigured_returns_home_with_notice(): void
    {
        config()->set('services.github.client_id', null);
        config()->set('services.github.client_secret', null);

        $this->get(route('auth.github.redirect'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'github_auth_unconfigured');
    }

    public function test_callback_creates_a_user_and_logs_them_in(): void
    {
        $githubUser = $this->fakeGitHubUser();
        Socialite::fake('github', $githubUser);

        $this->get(route('auth.github.callback'))
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'github_id' => 123456789,
            'github_username' => 'phoenix',
            'is_admin' => 0,
        ]);
    }

    public function test_callback_reuses_an_existing_user_and_updates_identity(): void
    {
        $user = User::factory()->create(['github_id' => 123456789, 'github_username' => 'old-name']);

        Socialite::fake('github', $this->fakeGitHubUser(nickname: 'phoenix'));

        $this->get(route('auth.github.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('phoenix', $user->fresh()->github_username);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_grants_admin_when_username_is_in_the_admin_list(): void
    {
        config()->set('services.github.admins', ['phoenix', 'sarah']);

        Socialite::fake('github', $this->fakeGitHubUser());

        $this->get(route('auth.github.callback'));

        $this->assertDatabaseHas('users', [
            'github_username' => 'phoenix',
            'is_admin' => 1,
        ]);
    }

    public function test_callback_does_not_grant_admin_for_other_users(): void
    {
        config()->set('services.github.admins', ['someone-else']);

        Socialite::fake('github', $this->fakeGitHubUser());

        $this->get(route('auth.github.callback'));

        $this->assertDatabaseHas('users', [
            'github_username' => 'phoenix',
            'is_admin' => 0,
        ]);
    }

    public function test_authenticated_user_can_sign_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('auth.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post(route('auth.logout'))->assertRedirect('/auth/github/redirect');
    }

    private function fakeGitHubUser(string $nickname = 'phoenix'): SocialiteUser
    {
        $user = (new SocialiteUser)->map([
            'id' => '123456789',
            'nickname' => $nickname,
            'name' => 'Phoenix Ray',
            'email' => 'phoenix@example.com',
            'avatar' => 'https://avatars.githubusercontent.com/u/123456789',
        ]);
        $user->token = 'oauth-token';
        $user->refreshToken = 'refresh-token';

        return $user;
    }
}
