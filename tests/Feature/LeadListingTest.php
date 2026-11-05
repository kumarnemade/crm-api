<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->manager()->create());
    }

    public function test_it_filters_by_status(): void
    {
        $match = Lead::factory()->status(LeadStatus::Qualified)->create();
        Lead::factory()->status(LeadStatus::New)->create();

        $this->assertListedIds([$match->id], '/api/leads?status=qualified');
    }

    public function test_it_filters_by_source(): void
    {
        $match = Lead::factory()->create(['source' => LeadSource::ColdCall]);
        Lead::factory()->create(['source' => LeadSource::Web]);

        $this->assertListedIds([$match->id], '/api/leads?source=cold_call');
    }

    public function test_it_filters_by_assigned_rep(): void
    {
        $rep = User::factory()->create();
        $match = Lead::factory()->assignedTo($rep)->create();
        Lead::factory()->assignedTo(User::factory()->create())->create();

        $this->assertListedIds([$match->id], "/api/leads?assigned_to={$rep->id}");
    }

    public function test_filters_combine(): void
    {
        $match = Lead::factory()->status(LeadStatus::Contacted)->create(['source' => LeadSource::Event]);
        Lead::factory()->status(LeadStatus::Contacted)->create(['source' => LeadSource::Web]);
        Lead::factory()->status(LeadStatus::New)->create(['source' => LeadSource::Event]);

        $this->assertListedIds([$match->id], '/api/leads?status=contacted&source=event');
    }

    /**
     * @param  array<string, string>  $attributes
     */
    #[DataProvider('searchableFields')]
    public function test_it_searches_each_field(array $attributes): void
    {
        $match = Lead::factory()->create($attributes);
        Lead::factory()->create(['name' => 'Unrelated', 'email' => 'other@example.com', 'company' => 'Globex']);

        $this->assertListedIds([$match->id], '/api/leads?search=needle');
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function searchableFields(): array
    {
        return [
            'name' => [['name' => 'Big Needle Inc']],
            'email' => [['email' => 'needle@example.com']],
            'company' => [['company' => 'Needle Works']],
        ];
    }

    public function test_it_paginates(): void
    {
        Lead::factory()->count(5)->create();

        $this->getJson('/api/leads?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_sorts_by_expected_value_in_both_directions(): void
    {
        $low = Lead::factory()->create(['expected_value' => '100.00']);
        $high = Lead::factory()->create(['expected_value' => '900.00']);
        $mid = Lead::factory()->create(['expected_value' => '500.00']);

        $this->assertListedIds([$low->id, $mid->id, $high->id], '/api/leads?sort=expected_value');
        $this->assertListedIds([$high->id, $mid->id, $low->id], '/api/leads?sort=-expected_value');
    }

    public function test_it_sorts_by_created_at_and_defaults_to_newest_first(): void
    {
        $oldest = Lead::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = Lead::factory()->create(['created_at' => now()->subDay()]);
        $middle = Lead::factory()->create(['created_at' => now()->subDays(2)]);

        $this->assertListedIds([$newest->id, $middle->id, $oldest->id], '/api/leads');
        $this->assertListedIds([$oldest->id, $middle->id, $newest->id], '/api/leads?sort=created_at');
    }

    #[DataProvider('invalidQueries')]
    public function test_it_rejects_invalid_query_parameters(string $query, string $field): void
    {
        $this->getJson("/api/leads?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'sort column not whitelisted' => ['sort=password', 'sort'],
            'unknown status' => ['status=archived', 'status'],
            'unknown source' => ['source=fax', 'source'],
            'page size too large' => ['per_page=101', 'per_page'],
        ];
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_leads(): void
    {
        $this->seedLeadsWithActivities(2);
        $queriesForFewLeads = $this->countQueries('/api/leads');

        $this->seedLeadsWithActivities(10);
        $queriesForManyLeads = $this->countQueries('/api/leads');

        $this->assertSame($queriesForFewLeads, $queriesForManyLeads);
    }

    private function seedLeadsWithActivities(int $count): void
    {
        Lead::factory()
            ->count($count)
            ->assignedTo(User::factory()->create())
            ->has(Activity::factory()->count(2))
            ->create();
    }

    private function countQueries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($uri)->assertOk();

        return count(DB::getQueryLog());
    }

    /**
     * @param  list<int>  $expectedIds
     */
    private function assertListedIds(array $expectedIds, string $uri): void
    {
        $this->assertSame($expectedIds, $this->getJson($uri)->assertOk()->json('data.*.id'));
    }
}
