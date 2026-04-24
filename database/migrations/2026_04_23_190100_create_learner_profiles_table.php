<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('grade_level')->nullable();
            $table->string('reading_level')->default('basic');
            $table->string('pace_level')->default('steady');
            $table->unsignedTinyInteger('confidence_level')->default(50);
            $table->unsignedTinyInteger('attention_window_minutes')->default(10);
            $table->string('preferred_language')->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_profiles');
    }
};
