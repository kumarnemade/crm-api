<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rep = User::factory()->create();
        Sanctum::actingAs($this->rep);
    }

    #[DataProvider('closedStatuses')]
    public function test_a_lead_without_activity_cannot_be_closed(string $status): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->status(LeadStatus::Qualified)->create();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => $status])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "A lead needs at least one logged activity before it can be marked {$status}.");

        $this->assertSame(LeadStatus::Qualified, $lead->fresh()->status);
    }

    #[DataProvider('closedStatuses')]
    public function test_a_lead_with_activity_can_be_closed(string $status): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();
        Activity::factory()->for($lead)->for($this->rep)->create();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => $status])
            ->assertOk()
            ->assertJsonPath('data.status', $status);

        $this->assertSame($status, $lead->fresh()->status->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedStatuses(): array
    {
        return ['won' => ['won'], 'lost' => ['lost']];
    }

    public function test_the_rule_also_binds_managers(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'won'])->assertUnprocessable();
    }

    public function test_activity_on_a_different_lead_does_not_count(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();
        Activity::factory()->for(Lead::factory()->assignedTo($this->rep))->for($this->rep)->create();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'won'])->assertUnprocessable();
    }

    public function test_a_lead_cannot_be_created_already_closed(): void
    {
        $this->postJson('/api/leads', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1 555 0100',
            'source' => 'web',
            'expected_value' => '100.00',
            'status' => 'won',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_open_statuses_need_no_activity(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'contacted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted');
    }

    public function test_an_unknown_status_gets_a_validation_error_not_a_server_error(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_other_fields_can_be_updated_without_sending_the_rest(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create(['name' => 'Before', 'phone' => '123']);

        $this->patchJson("/api/leads/{$lead->id}", ['name' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.name', 'After')
            ->assertJsonPath('data.phone', '123');
    }

    public function test_update_cannot_be_used_to_reassign_a_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();
        $otherRep = User::factory()->create();

        $this->patchJson("/api/leads/{$lead->id}", ['assigned_to' => $otherRep->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->assertSame($this->rep->id, $lead->fresh()->assigned_to);
    }
}
