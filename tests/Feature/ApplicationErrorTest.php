<?php

namespace Tests\Feature;

use App\Models\ApplicationError;
use App\Models\User;
use App\Services\ApplicationErrorRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ApplicationErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_exception_is_not_recorded(): void
    {
        try {
            throw ValidationException::withMessages(['email' => 'Required']);
        } catch (ValidationException $exception) {
            report($exception);
        }

        $this->assertDatabaseCount('application_errors', 0);
    }

    public function test_reportable_exception_is_recorded_and_grouped(): void
    {
        report(new RuntimeException('Deliberate failure for error store'));
        report(new RuntimeException('Deliberate failure for error store'));

        $this->assertDatabaseCount('application_errors', 1);
        $row = ApplicationError::query()->first();
        $this->assertSame('Deliberate failure for error store', $row->message);
        $this->assertSame(RuntimeException::class, $row->exception_class);
        $this->assertSame('new', $row->status);
        $this->assertSame('exception', $row->category);
        $this->assertGreaterThanOrEqual(2, $row->occurrence_count);
    }

    public function test_admin_can_list_resolve_and_view_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $error = ApplicationError::query()->create([
            'fingerprint' => 'abc123',
            'occurrence_count' => 3,
            'category' => 'exception',
            'status' => 'new',
            'level' => 'error',
            'message' => 'Broken checkout',
            'exception_class' => RuntimeException::class,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/errors?status=unresolved')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $error->uuid)
            ->assertJsonPath('data.0.occurrence_count', 3)
            ->assertJsonPath('meta.open_count', 1);

        $this->getJson('/api/admin/errors/summary')
            ->assertOk()
            ->assertJsonPath('unresolved', 1)
            ->assertJsonPath('top.0.uuid', $error->uuid);

        $this->patchJson("/api/admin/errors/{$error->uuid}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertSame('resolved', $error->fresh()->status);
        $this->assertNotNull($error->fresh()->resolved_at);

        $this->deleteJson("/api/admin/errors/{$error->uuid}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('application_errors', ['uuid' => $error->uuid]);
    }

    public function test_guest_and_non_admin_cannot_access_errors_api(): void
    {
        $error = ApplicationError::query()->create([
            'fingerprint' => 'secret',
            'level' => 'error',
            'message' => 'Secret',
            'status' => 'new',
            'category' => 'system',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->getJson('/api/admin/errors')->assertUnauthorized();
        $this->getJson('/api/admin/errors/summary')->assertUnauthorized();
        $this->getJson("/api/admin/errors/{$error->uuid}")->assertUnauthorized();

        $html = $this->get('/api/admin/errors', ['Accept' => 'text/html']);
        $html->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
        $this->assertStringNotContainsString('Route [login]', $html->getContent());

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/errors')->assertForbidden();
        $this->patchJson("/api/admin/errors/{$error->uuid}", ['status' => 'resolved'])->assertForbidden();
        $this->deleteJson("/api/admin/errors/{$error->uuid}")->assertForbidden();
    }

    public function test_recorder_skips_validation_directly(): void
    {
        $recorder = app(ApplicationErrorRecorder::class);

        $recorder->recordThrowable(ValidationException::withMessages(['name' => 'Required']));

        $this->assertDatabaseCount('application_errors', 0);
    }

    public function test_api_500_json_hides_debug_when_app_debug_false(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/__test-boom');

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Something went wrong. Please try again.')
            ->assertJsonPath('code', 'server_error')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }
}
