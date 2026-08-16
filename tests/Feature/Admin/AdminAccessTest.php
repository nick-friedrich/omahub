<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_forbidden_from_the_admin_area(): void
    {
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_row_admin_user_is_forbidden_from_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            route('admin.dashboard'),
            route('admin.submissions.index'),
            route('admin.plugins.index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_admin_user_can_access_the_admin_area(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.submissions.index'))->assertOk();
        $this->get(route('admin.plugins.index'))->assertOk();
    }
}
