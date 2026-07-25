<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_their_phone_number()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'phone' => $user->phone,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_their_email_address()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'phone' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_deactivated_users_can_not_authenticate()
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('phone');
    }

    public function test_successful_login_stamps_last_login_at()
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
