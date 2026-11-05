<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $rep;

    private User $otherRep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->rep = User::factory()->create();
        $this->otherRep = User::factory()->create();
    }

    public function test_a_rep_only_lists_their_own_leads(): void
    {
        $own = Lead::factory()->assignedTo($this->rep)->create();
        Lead::factory()->assignedTo($this->otherRep)->create();
        Lead::factory()->create();

        Sanctum::actingAs($this->rep);

        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_a_manager_lists_all_leads_including_unassigned_ones(): void
    {
        Lead::factory()->assignedTo($this->rep)->create();
        Lead::factory()->assignedTo($this->otherRep)->create();
        Lead::factory()->create();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/leads')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_search_cannot_reach_beyond_a_reps_own_leads(): void
    {
        $own = Lead::factory()->assignedTo($this->rep)->create(['name' => 'Acme One', 'company' => 'Acme']);
        Lead::factory()->assignedTo($this->otherRep)->create(['name' => 'Acme Two', 'company' => 'Acme']);
        Lead::factory()->assignedTo($this->otherRep)->create(['name' => 'Other', 'email' => 'acme@example.com']);

        Sanctum::actingAs($this->rep);

        $this->getJson('/api/leads?search=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_a_rep_cannot_use_the_assigned_to_filter_to_see_another_reps_leads(): void
    {
        Lead::factory()->assignedTo($this->otherRep)->create();

        Sanctum::actingAs($this->rep);

        $this->getJson("/api/leads?assigned_to={$this->otherRep->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_rep_cannot_view_another_reps_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->otherRep)->create();

        Sanctum::actingAs($this->rep);

        $this->getJson("/api/leads/{$lead->id}")
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_a_rep_cannot_view_an_unassigned_lead(): void
    {
        $lead = Lead::factory()->create();

        Sanctum::actingAs($this->rep);

        $this->getJson("/api/leads/{$lead->id}")->assertForbidden();
    }

    public function test_a_rep_cannot_update_another_reps_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->otherRep)->create(['name' => 'Original']);

        Sanctum::actingAs($this->rep);

        $this->patchJson("/api/leads/{$lead->id}", ['name' => 'Changed'])->assertForbidden();

        $this->assertSame('Original', $lead->fresh()->name);
    }

    public function test_viewing_a_lead_includes_its_rep_and_activities_newest_first(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();
        $older = Activity::factory()->for($lead)->for($this->rep)->create(['occurred_at' => now()->subDays(2)]);
        $newer = Activity::factory()->for($lead)->for($this->manager)->create(['occurred_at' => now()->subDay()]);

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.assigned_rep.id', $this->rep->id)
            ->assertJsonPath('data.activities.0.id', $newer->id)
            ->assertJsonPath('data.activities.0.logged_by.id', $this->manager->id)
            ->assertJsonPath('data.activities.1.id', $older->id);
    }

    public function test_a_missing_lead_returns_a_404_without_exposing_the_model(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/leads/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_a_lead_created_by_a_rep_is_assigned_to_them_and_starts_as_new(): void
    {
        Sanctum::actingAs($this->rep);

        $response = $this->postJson('/api/leads', $this->leadPayload());

        $response->assertCreated()
            ->assertJsonPath('data.assigned_to', $this->rep->id)
            ->assertJsonPath('data.assigned_rep.id', $this->rep->id)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.expected_value', '1500.50');

        $this->getJson('/api/leads/'.$response->json('data.id'))->assertOk();
    }

    public function test_a_rep_cannot_assign_a_new_lead_to_someone_else(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson('/api/leads', [...$this->leadPayload(), 'assigned_to' => $this->otherRep->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_a_manager_can_create_an_unassigned_lead(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/leads', $this->leadPayload())
            ->assertCreated()
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.assigned_rep', null);
    }

    public function test_a_manager_can_create_a_lead_for_a_rep(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/leads', [...$this->leadPayload(), 'assigned_to' => $this->rep->id])
            ->assertCreated()
            ->assertJsonPath('data.assigned_rep.id', $this->rep->id);
    }

    public function test_creating_a_lead_validates_its_fields(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson('/api/leads', ['email' => 'not-an-email', 'source' => 'fax', 'expected_value' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'source', 'expected_value']);
    }

    public function test_expected_value_rejects_more_than_two_decimal_places(): void
    {
        Sanctum::actingAs($this->rep);

        $this->postJson('/api/leads', [...$this->leadPayload(), 'expected_value' => '10.123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_value');
    }

    /**
     * @return array<string, mixed>
     */
    private function leadPayload(): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1 555 0100',
            'company' => 'Acme',
            'source' => 'referral',
            'expected_value' => '1500.50',
        ];
    }
}
