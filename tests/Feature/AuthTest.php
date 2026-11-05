<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_working_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'rep')
            ->assertJsonMissingPath('data.user.password');

        $this->assertCount(1, $user->tokens);
    }

    public function test_wrong_password_is_rejected_without_issuing_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertCount(0, $user->tokens);
    }

    public function test_unknown_email_gets_the_same_error_as_a_wrong_password(): void
    {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }

    public function test_protected_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/leads')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_an_issued_token_authenticates_api_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/leads')->assertOk();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
