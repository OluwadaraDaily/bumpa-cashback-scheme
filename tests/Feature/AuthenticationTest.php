<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sign_up_and_receive_a_token(): void
    {
        $response = $this->postJson('/signup', [
            'username' => 'ada-lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.username', 'ada-lovelace')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['user' => ['id', 'username', 'email'], 'token']);

        $this->assertDatabaseHas('users', [
            'username' => 'ada-lovelace',
            'email' => 'ada@example.com',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_cannot_register_with_an_existing_email_or_username(): void
    {
        User::factory()->create([
            'username' => 'ada-lovelace',
            'email' => 'ada@example.com',
        ]);

        $response = $this->postJson('/signup', [
            'username' => 'ada-lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token', 'token_type']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_cannot_log_in_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_is_required_to_view_the_current_user(): void
    {
        $user = User::factory()->create();

        $this->getJson('/me')->assertUnauthorized();

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_user_can_log_out_and_revoke_the_current_token(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('test');

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);

        Auth::forgetGuards();

        $this->withToken($accessToken->plainTextToken)
            ->getJson('/me')
            ->assertUnauthorized();
    }
}
