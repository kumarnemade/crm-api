<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50);
            $table->string('company')->nullable();
            $table->string('source', 20);
            $table->string('status', 20)->default('new');
            $table->decimal('expected_value', 12, 2);
            $table->timestamps();

            // Covers the rep report's GROUP BY, so it never has to read the table rows.
            $table->index(['assigned_to', 'status', 'expected_value']);
            $table->index('status');
            $table->index('source');
            $table->index('created_at');
            $table->index('expected_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
