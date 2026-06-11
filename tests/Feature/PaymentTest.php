<?php

namespace Tests\Feature;

use App\Events\PaymentStatusChanged;
use App\Jobs\ProcessPayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/user')->assertStatus(401);
        $this->getJson('/api/v1/balance')->assertStatus(401);
        $this->getJson('/api/v1/payments')->assertStatus(401);
        $this->postJson('/api/v1/payments', ['amount' => 100])->assertStatus(401);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securepassword123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_user_can_login_and_get_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ]
                ]
            ]);
    }

    public function test_authorized_user_can_access_endpoints(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
    }

    public function test_payment_creation_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/payments', [
            'amount' => 150.50,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'amount',
                    'status',
                    'reference',
                ]
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'amount' => 150.50,
            'status' => 'pending',
        ]);

        $payment = Payment::first();

        Queue::assertPushed(ProcessPayment::class, function ($job) use ($payment) {
            $reflected = new \ReflectionClass($job);
            $property = $reflected->getProperty('payment');
            $property->setAccessible(true);
            $jobPayment = $property->getValue($job);
            return $jobPayment->id === $payment->id;
        });
    }

    public function test_balance_endpoint_calculates_only_successful_payments(): void
    {
        $user = User::factory()->create();

        $user->payments()->create(['amount' => 100, 'status' => 'successful', 'reference' => 'ref1']);
        $user->payments()->create(['amount' => 50.50, 'status' => 'successful', 'reference' => 'ref2']);
        $user->payments()->create(['amount' => 200, 'status' => 'failed', 'reference' => 'ref3']);
        $user->payments()->create(['amount' => 300, 'status' => 'pending', 'reference' => 'ref4']);
        $user->payments()->create(['amount' => 75, 'status' => 'refunded', 'reference' => 'ref5']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 150.50,
                ]
            ]);
    }

    public function test_private_channel_authentication(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // User A trying to authenticate for their own channel
        $response = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-customer.' . $userA->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(200);

        // User A trying to authenticate for User B's channel
        $response = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-customer.' . $userB->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(403);
    }

    public function test_payment_status_changed_event_broadcasts_on_private_channel(): void
    {
        Event::fake([PaymentStatusChanged::class]);

        $user = User::factory()->create();
        $payment = $user->payments()->create([
            'amount' => 100,
            'status' => 'pending',
            'reference' => 'ref123',
        ]);

        $payment->update(['status' => 'successful']);
        PaymentStatusChanged::dispatch($payment);

        Event::assertDispatched(PaymentStatusChanged::class, function ($event) use ($payment, $user) {
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertEquals('private-customer.' . $user->id, $channels[0]->name);

            $payload = $event->broadcastWith();
            return $payload['paymentId'] === $payment->id
                && $payload['status'] === 'successful'
                && $payload['customerId'] === $user->id;
        });
    }

    public function test_spa_realtime_updates_using_mocked_websocket_events(): void
    {
        Event::fake([PaymentStatusChanged::class]);

        $user = User::factory()->create();
        $payment = $user->payments()->create([
            'amount' => 500,
            'status' => 'pending',
            'reference' => 'pay_ws_test',
        ]);

        $payment->update(['status' => 'successful']);
        PaymentStatusChanged::dispatch($payment);

        Event::assertDispatched(PaymentStatusChanged::class, function ($event) use ($user, $payment) {
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertEquals('private-customer.' . $user->id, $channels[0]->name);

            $payload = $event->broadcastWith();
            return $payload['paymentId'] === $payment->id
                && $payload['status'] === 'successful'
                && $payload['customerId'] === $user->id;
        });
    }
}
