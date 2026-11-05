<?php

namespace Tests\Feature;

use App\Jobs\NotifyRepOfAssignment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->manager = User::factory()->manager()->create();
        $this->rep = User::factory()->create();
    }

    public function test_a_manager_can_assign_an_unassigned_lead(): void
    {
        $lead = Lead::factory()->create();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->rep->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_rep.id', $this->rep->id);

        $this->assertSame($this->rep->id, $lead->fresh()->assigned_to);
        Queue::assertPushed(NotifyRepOfAssignment::class, fn ($job) => $job->lead->is($lead));
    }

    public function test_reassigning_moves_visibility_to_the_new_rep(): void
    {
        $newRep = User::factory()->create();
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $newRep->id])->assertOk();

        Sanctum::actingAs($this->rep);
        $this->getJson("/api/leads/{$lead->id}")->assertForbidden();

        Sanctum::actingAs($newRep);
        $this->getJson("/api/leads/{$lead->id}")->assertOk();
    }

    public function test_a_rep_cannot_assign_even_their_own_lead(): void
    {
        $otherRep = User::factory()->create();
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        Sanctum::actingAs($this->rep);

        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $otherRep->id])->assertForbidden();

        $this->assertSame($this->rep->id, $lead->fresh()->assigned_to);
        Queue::assertNothingPushed();
    }

    public function test_a_lead_cannot_be_assigned_to_a_manager(): void
    {
        $lead = Lead::factory()->create();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->manager->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.assigned_to.0', 'Leads can only be assigned to an existing rep.');

        $this->assertNull($lead->fresh()->assigned_to);
    }

    public function test_the_target_rep_must_exist_and_be_provided(): void
    {
        $lead = Lead::factory()->create();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->postJson("/api/leads/{$lead->id}/assign")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_assigning_to_the_current_rep_does_not_notify_again(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->rep->id])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_a_manager_creating_a_lead_for_a_rep_notifies_them(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/leads', [...$this->leadPayload(), 'assigned_to' => $this->rep->id])->assertCreated();

        Queue::assertPushed(NotifyRepOfAssignment::class);
    }

    public function test_a_rep_is_not_notified_about_a_lead_they_created_themselves(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_the_job_logs_the_assignment(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        Log::shouldReceive('info')->once()->with('Lead assigned to rep.', [
            'lead_id' => $lead->id,
            'rep_id' => $this->rep->id,
        ]);

        (new NotifyRepOfAssignment($lead))->handle();
    }

    /**
     * @return array<string, string>
     */
    private function leadPayload(): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1 555 0100',
            'source' => 'web',
            'expected_value' => '100.00',
        ];
    }
}
