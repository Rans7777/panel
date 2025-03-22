<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_access_token()
    {
        $this->assertTrue(Schema::hasTable('access_tokens'), 'access_tokensテーブルが存在しません');
        $user = User::factory()->create();
        $response = $this->withoutExceptionHandling()
            ->actingAs($user)
            ->getJson('/api/create-access-token');
        $response->assertStatus(200)
            ->assertJsonStructure(['access_token']);
        $this->assertDatabaseHas('access_tokens', [
            'access_token' => $response->json('access_token')
        ]);
    }

    #[Test]
    public function it_can_get_latest_access_token()
    {
        $accessToken = 'test-token-123';
        DB::table('access_tokens')->insert([
            'access_token' => $accessToken,
            'created_at' => Carbon::now(config('app.timezone')),
        ]);
        $this->markTestSkipped();
    }

    #[Test]
    public function it_can_verify_valid_token()
    {
        $user = User::factory()->create();
        $validToken = 'valid-token-123';
        DB::table('access_tokens')->insert([
            'access_token' => $validToken,
            'created_at' => Carbon::now(config('app.timezone')),
        ]);
        $response = $this->actingAs($user)
            ->getJson("/api/access-token/{$validToken}/validity");
        $response->assertStatus(200)
            ->assertJson([
                'valid' => true
            ]);
    }

    #[Test]
    public function it_returns_invalid_for_expired_token()
    {
        $user = User::factory()->create();
        $expiredToken = 'expired-token-123';
        DB::table('access_tokens')->insert([
            'access_token' => $expiredToken,
            'created_at' => Carbon::now(config('app.timezone'))->subMinutes(10),
        ]);
        $response = $this->actingAs($user)
            ->getJson("/api/access-token/{$expiredToken}/validity");
        $response->assertStatus(401)
            ->assertJson([
                'valid' => false
            ]);
    }

    #[Test]
    public function it_returns_invalid_for_nonexistent_token()
    {
        $user = User::factory()->create();
        $nonExistentToken = 'non-existent-token';
        $response = $this->actingAs($user)
            ->getJson("/api/access-token/{$nonExistentToken}/validity");
        $response->assertStatus(401)
            ->assertJson([
                'valid' => false
            ]);
    }
}
