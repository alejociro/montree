<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_time_user_can_set_password_without_current_password(): void
    {
        $user = User::factory()->needsPasswordSetup()->create();

        $this->assertTrue($user->mustSetPassword());

        $response = $this
            ->actingAs($user)
            ->from(route('account.bookings'))
            ->post(route('user-password.setup'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotNull($user->password_set_at);
        $this->assertFalse($user->mustSetPassword());
    }

    public function test_user_who_already_set_password_cannot_use_setup_endpoint(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->mustSetPassword());

        $response = $this
            ->actingAs($user)
            ->post(route('user-password.setup'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertForbidden();
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = User::factory()->needsPasswordSetup()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('account.bookings'))
            ->post(route('user-password.setup'), [
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->mustSetPassword());
    }

    public function test_guests_cannot_set_password(): void
    {
        $response = $this->post(route('user-password.setup'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
    }
}
