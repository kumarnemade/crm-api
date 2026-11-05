<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assigned_to' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->optional(0.8)->company(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => LeadStatus::New,
            'expected_value' => fake()->randomFloat(2, 500, 50000),
        ];
    }

    public function assignedTo(User $rep): static
    {
        return $this->state(['assigned_to' => $rep->id]);
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
