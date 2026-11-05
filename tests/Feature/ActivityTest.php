<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rep = User::factory()->create();
        $this->lead = Lead::factory()->assignedTo($this->rep)->create();
    }

    public function test_a_rep_can_log_an_activity_on_their_lead(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", [
            'type' => 'call',
            'body' => 'Spoke about pricing.',
            'occurred_at' => '2026-01-15T10:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'call')
            ->assertJsonPath('data.lead_id', $this->lead->id)
            ->assertJsonPath('data.logged_by.id', $this->rep->id);

        $this->assertDatabaseHas('activities', [
            'lead_id' => $this->lead->id,
            'user_id' => $this->rep->id,
            'body' => 'Spoke about pricing.',
            'occurred_at' => '2026-01-15 10:00:00',
        ]);
    }

    public function test_occurred_at_defaults_to_now(): void
    {
        $this->freezeSecond();

        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", ['type' => 'note', 'body' => 'Quick note.'])
            ->assertCreated();

        $this->assertTrue($this->lead->activities()->sole()->occurred_at->equalTo(now()));
    }

    public function test_occurred_at_with_an_offset_is_stored_as_the_correct_instant(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", [
            'type' => 'meeting',
            'body' => 'Met in Mumbai.',
            'occurred_at' => '2026-01-15T10:00:00+05:30',
        ])->assertCreated();

        $this->assertDatabaseHas('activities', ['occurred_at' => '2026-01-15 04:30:00']);
    }

    public function test_the_logger_comes_from_the_token_not_the_request_body(): void
    {
        $otherRep = User::factory()->create();

        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", [
            'type' => 'note',
            'body' => 'Spoofed?',
            'user_id' => $otherRep->id,
        ])->assertCreated()->assertJsonPath('data.logged_by.id', $this->rep->id);
    }

    public function test_a_manager_can_log_an_activity_on_any_lead(): void
    {
        $manager = User::factory()->manager()->create();

        Sanctum::actingAs($manager);

        $this->postJson("/api/leads/{$this->lead->id}/activities", ['type' => 'email', 'body' => 'Followed up.'])
            ->assertCreated()
            ->assertJsonPath('data.logged_by.id', $manager->id);
    }

    public function test_a_rep_cannot_log_an_activity_on_another_reps_lead(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/leads/{$this->lead->id}/activities", ['type' => 'call', 'body' => 'Nope.'])
            ->assertForbidden();

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_it_validates_type_and_body(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", ['type' => 'fax'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'body']);
    }

    public function test_it_rejects_an_activity_dated_in_the_future(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$this->lead->id}/activities", [
            'type' => 'meeting',
            'body' => 'Next week.',
            'occurred_at' => now()->addWeek()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
    }

    public function test_logging_an_activity_lets_the_lead_be_closed(): void
    {
        Sanctum::actingAs($this->rep);

        $this->patchJson("/api/leads/{$this->lead->id}", ['status' => 'won'])->assertUnprocessable();

        $this->postJson("/api/leads/{$this->lead->id}/activities", ['type' => 'call', 'body' => 'Signed.'])
            ->assertCreated();

        $this->patchJson("/api/leads/{$this->lead->id}", ['status' => 'won'])->assertOk();
    }
}
