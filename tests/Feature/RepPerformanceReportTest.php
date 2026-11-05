<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->rep = User::factory()->create(['name' => 'Alice']);
    }

    public function test_it_summarises_a_reps_leads(): void
    {
        Lead::factory()->assignedTo($this->rep)->status(LeadStatus::New)->create(['expected_value' => '100.50']);
        Lead::factory()->assignedTo($this->rep)->status(LeadStatus::Won)->create(['expected_value' => '200.25']);
        Lead::factory()->assignedTo($this->rep)->status(LeadStatus::Won)->create(['expected_value' => '300.00']);
        Lead::factory()->assignedTo($this->rep)->status(LeadStatus::Lost)->create(['expected_value' => '50.00']);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/rep-performance')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'rep' => ['id' => $this->rep->id, 'name' => 'Alice'],
                'total_leads' => 4,
                'leads_by_status' => ['new' => 1, 'contacted' => 0, 'qualified' => 0, 'won' => 2, 'lost' => 1],
                'total_expected_value' => '650.75',
                'won_expected_value' => '500.25',
                'activity_count' => 0,
            ]]]);
    }

    public function test_multiple_activities_on_a_lead_do_not_inflate_the_totals(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->status(LeadStatus::Won)->create(['expected_value' => '1000.00']);
        Activity::factory()->count(3)->for($lead)->for($this->rep)->create();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/rep-performance')
            ->assertJsonPath('data.0.total_leads', 1)
            ->assertJsonPath('data.0.leads_by_status.won', 1)
            ->assertJsonPath('data.0.total_expected_value', '1000.00')
            ->assertJsonPath('data.0.won_expected_value', '1000.00')
            ->assertJsonPath('data.0.activity_count', 3);
    }

    public function test_a_rep_with_no_leads_still_appears_with_zeroes(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/rep-performance')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_leads', 0)
            ->assertJsonPath('data.0.leads_by_status.new', 0)
            ->assertJsonPath('data.0.total_expected_value', '0.00')
            ->assertJsonPath('data.0.won_expected_value', '0.00')
            ->assertJsonPath('data.0.activity_count', 0);
    }

    public function test_a_manager_sees_every_rep_but_no_managers_or_unassigned_leads(): void
    {
        $otherRep = User::factory()->create(['name' => 'Bob']);
        Lead::factory()->assignedTo($this->rep)->create();
        Lead::factory()->count(2)->assignedTo($otherRep)->create();
        Lead::factory()->create();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/rep-performance')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rep.name', 'Alice')
            ->assertJsonPath('data.0.total_leads', 1)
            ->assertJsonPath('data.1.rep.name', 'Bob')
            ->assertJsonPath('data.1.total_leads', 2);
    }

    public function test_a_rep_sees_only_their_own_row(): void
    {
        $otherRep = User::factory()->create();
        Lead::factory()->assignedTo($this->rep)->create(['expected_value' => '10.00']);
        Lead::factory()->assignedTo($otherRep)->create(['expected_value' => '99.00']);

        Sanctum::actingAs($this->rep);

        $this->getJson('/api/reports/rep-performance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rep.id', $this->rep->id)
            ->assertJsonPath('data.0.total_expected_value', '10.00');
    }

    public function test_activity_count_covers_all_activity_on_a_reps_leads(): void
    {
        $lead = Lead::factory()->assignedTo($this->rep)->create();
        Activity::factory()->for($lead)->for($this->rep)->create();
        Activity::factory()->for($lead)->for($this->manager)->create();
        Activity::factory()->for(Lead::factory())->for($this->rep)->create();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/rep-performance')->assertJsonPath('data.0.activity_count', 2);
    }

    public function test_the_report_is_a_single_query_however_many_reps_exist(): void
    {
        User::factory()->count(5)->create()->each(function (User $rep) {
            Lead::factory()->count(2)->assignedTo($rep)->has(Activity::factory()->count(2)->for($rep))->create();
        });

        Sanctum::actingAs($this->manager);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/reports/rep-performance')->assertOk()->assertJsonCount(6, 'data');

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_the_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/rep-performance')->assertUnauthorized();
    }
}
