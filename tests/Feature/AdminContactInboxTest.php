<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContactInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_contact_submissions_start_unread(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', [
            'name' => 'Fresh Customer',
            'email' => 'fresh@example.com',
            'message' => 'Please help with my order.',
        ])->assertCreated();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'fresh@example.com',
            'read_at' => null,
            'read_by_user_id' => null,
        ]);
    }

    public function test_admin_inbox_supports_search_pagination_and_unread_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $this->message('Asha', 'asha@example.com', 'Need a delivery update.');
        $this->message('Ravi', 'ravi@example.com', 'Product question.', now());
        $this->message('Other', 'other@example.com', 'Unrelated.');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/contact-messages?search=delivery&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'asha@example.com')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.unread_count', 2);
    }

    public function test_mark_read_is_idempotent_and_preserves_the_first_reader(): void
    {
        $firstAdmin = User::factory()->admin()->create();
        $secondAdmin = User::factory()->admin()->create();
        $message = $this->message('Customer', 'customer@example.com', 'Hello');

        Sanctum::actingAs($firstAdmin);
        $this->patchJson("/api/admin/contact-messages/{$message->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('unread_count', 0);

        $firstReadAt = $message->fresh()->read_at;

        Sanctum::actingAs($secondAdmin);
        $this->patchJson("/api/admin/contact-messages/{$message->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $message->refresh();
        $this->assertTrue($message->read_at->equalTo($firstReadAt));
        $this->assertSame($firstAdmin->id, $message->read_by_user_id);
    }

    public function test_mark_all_and_delete_keep_the_unread_count_synchronized(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->message('One', 'one@example.com', 'First');
        $this->message('Two', 'two@example.com', 'Second');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/contact-messages/{$first->id}")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->patchJson('/api/admin/contact-messages/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, ContactMessage::query()->unread()->count());
        $this->assertDatabaseMissing('contact_messages', ['id' => $first->id]);
    }

    public function test_contact_inbox_requires_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/contact-messages')->assertForbidden();
        $this->patchJson('/api/admin/contact-messages/read-all')->assertForbidden();
        $this->getJson('/api/admin/navigation-counts')->assertForbidden();
    }

    public function test_navigation_counts_only_include_inventory_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $this->message('Unread', 'unread@example.com', 'Unread contact');
        $this->message('Read', 'read@example.com', 'Read contact', now());

        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => LowStockNotification::class,
            'data' => ['product_id' => 1],
        ]);
        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\UnrelatedNotification',
            'data' => [],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/navigation-counts')
            ->assertOk()
            ->assertJson([
                'inventory_unread_count' => 1,
                'contact_unread_count' => 1,
            ]);

        $this->patchJson('/api/admin/notifications/inventory/read-all')
            ->assertOk()
            ->assertJsonPath('inventory_unread_count', 0);

        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    private function message(
        string $name,
        string $email,
        string $message,
        $readAt = null,
    ): ContactMessage {
        $contact = ContactMessage::query()->create([
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]);

        if ($readAt) {
            $contact->forceFill(['read_at' => $readAt])->save();
        }

        return $contact;
    }
}
