<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_is_available_and_linked_from_the_site(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('feedback@nick-friedrich.de');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('privacy'), escape: false);
    }

    public function test_impressum_is_available_and_linked_from_the_site(): void
    {
        $this->get(route('impressum'))
            ->assertOk()
            ->assertSee('Impressum')
            ->assertSee('Butterbauernstieg 4');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('impressum'), escape: false);
    }
}
