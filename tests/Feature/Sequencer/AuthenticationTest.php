<?php

namespace Tests\Feature\Sequencer;

use App\Models\ActivityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    public function test_guests_are_redirected_from_every_page(): void
    {
        foreach (['/', '/leads', '/bulks', '/email-activity', '/settings/mail', '/outreach/sequences', '/outreach/activity', '/outreach/profile'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_a_user_can_register_log_in_and_log_out(): void
    {
        $this->post('/signup', [
            'name' => 'New Person', 'username' => 'newperson', 'email' => 'new@example.com',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['login' => 'newperson', 'password' => 'a-long-password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->get('/outreach/sequences')->assertOk();
    }

    public function test_wrong_credentials_are_rejected_without_revealing_which_part_was_wrong(): void
    {
        $this->makeUser(['username' => 'known_user']);

        $this->from('/login')->post('/login', ['login' => 'known_user', 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'unknown_user', 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_password_reset_flow_end_to_end(): void
    {
        Notification::fake();
        $user = $this->makeUser(['email' => 'reset@example.com']);

        $this->get('/forgot-password')->assertOk();

        $this->post('/forgot-password', ['email' => 'reset@example.com'])->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get('/reset-password/'.$token.'?email=reset@example.com')->assertOk()->assertSee($token);

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'reset@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('brand-new-pass', $user->refresh()->password));

        // The token is single use.
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'reset@example.com', 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_the_reset_form_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('success', 'If an account exists for that email address, a reset link is on its way.');

        Notification::assertNothingSent();
    }

    public function test_profile_timezone_and_password_can_be_changed(): void
    {
        $user = $this->makeUser(['password' => 'old-password-1']);
        $this->actingAs($user);

        $this->put('/outreach/profile', ['name' => 'Renamed', 'email' => $user->email, 'timezone' => 'Asia/Kolkata'])->assertSessionHas('success');
        $this->assertSame('Asia/Kolkata', $user->refresh()->timezone);
        $this->assertSame('Renamed', $user->name);

        $this->put('/outreach/profile', ['name' => 'X', 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');

        $this->put('/outreach/profile/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrors('current_password');

        $this->put('/outreach/profile/password', ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-1', $user->refresh()->password));
    }

    public function test_user_settings_become_defaults_for_new_sequences(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->put('/outreach/profile/settings', [
            'daily_limit' => 37, 'sending_start_time' => '08:30', 'sending_end_time' => '16:00', 'sending_days' => [1, 3, 5],
        ])->assertSessionHas('success');

        $this->assertSame(37, $user->refresh()->setting('daily_limit'));
        $this->get('/outreach/sequences/create')->assertOk()->assertSee('value="37"', false)->assertSee('08:30');
    }

    public function test_times_are_displayed_in_the_users_timezone(): void
    {
        $user = $this->makeUser(['timezone' => 'Asia/Kolkata']);
        $lead = $this->makeLead();
        ActivityEvent::create([
            'lead_id' => $lead->id, 'type' => 'email_sent', 'description' => 'x',
            'occurred_at' => CarbonImmutable::parse('2026-03-04 04:00:00', 'UTC'),   // a moment given in UTC
        ]);

        $this->actingAs($user)->get('/outreach/activity')->assertOk()->assertSee('09:30');   // 04:00 UTC = 09:30 IST
        $this->get('/outreach/activity?lead='.$lead->id)->assertOk()->assertSee('Back to Leads');
    }
}
