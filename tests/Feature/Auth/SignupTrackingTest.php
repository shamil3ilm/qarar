<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SignupTrackingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_user_fillable_includes_signup_tracking_fields(): void
    {
        $user     = new User();
        $fillable = $user->getFillable();

        foreach ([
            'registration_source', 'utm_source', 'utm_medium', 'utm_campaign',
            'utm_term', 'utm_content', 'referral_code', 'registration_device_type',
            'registration_ip', 'invited_by_user_id',
        ] as $field) {
            $this->assertContains($field, $fillable, "Field {$field} missing from fillable");
        }
    }

    public function test_registration_stores_utm_and_source_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                     => 'Ahmed Al-Rashid',
            'email'                    => 'ahmed@example.com',
            'password'                 => 'Password123!',
            'password_confirmation'    => 'Password123!',
            'organization_name'        => 'Acme Corp',
            'country_code'             => 'AE',
            'registration_source'      => 'web',
            'utm_source'               => 'google',
            'utm_medium'               => 'cpc',
            'utm_campaign'             => 'gcc-launch-q2',
            'utm_term'                 => 'erp software',
            'utm_content'              => 'banner-a',
            'referral_code'            => 'PARTNER-XYZ',
            'registration_device_type' => 'web',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'ahmed@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('web', $user->registration_source);
        $this->assertSame('google', $user->utm_source);
        $this->assertSame('cpc', $user->utm_medium);
        $this->assertSame('gcc-launch-q2', $user->utm_campaign);
        $this->assertSame('erp software', $user->utm_term);
        $this->assertSame('banner-a', $user->utm_content);
        $this->assertSame('PARTNER-XYZ', $user->referral_code);
        $this->assertSame('web', $user->registration_device_type);
        $this->assertNotNull($user->registration_ip);
    }

    public function test_registration_works_without_utm_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Sara Hassan',
            'email'                 => 'sara@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name'     => 'Beta Corp',
            'country_code'          => 'SA',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'sara@example.com')->first();
        $this->assertNull($user->utm_source);
        $this->assertNull($user->registration_source);
    }

    public function test_invalid_registration_source_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name'     => 'Test Corp',
            'country_code'          => 'SA',
            'registration_source'   => 'invalid_source',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_source']);
    }
}