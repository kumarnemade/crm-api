<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Every seeded user's password is "password".
     */
    public function run(): void
    {
        User::factory()->manager()->create(['name' => 'Morgan Manager', 'email' => 'manager@crm.test']);

        $reps = [
            User::factory()->create(['name' => 'Alice Rep', 'email' => 'alice@crm.test']),
            User::factory()->create(['name' => 'Bob Rep', 'email' => 'bob@crm.test']),
            User::factory()->create(['name' => 'Carol Rep', 'email' => 'carol@crm.test']),
        ];

        foreach ($reps as $rep) {
            foreach (LeadStatus::cases() as $status) {
                Lead::factory()
                    ->count(2)
                    ->assignedTo($rep)
                    ->status($status)
                    ->has(Activity::factory()->count($status === LeadStatus::New ? 0 : 2)->for($rep))
                    ->create();
            }
        }

        Lead::factory()->count(5)->create();
    }
}
