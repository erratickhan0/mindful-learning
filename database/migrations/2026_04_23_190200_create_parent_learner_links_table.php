<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_learner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('guardian');
            $table->timestamps();

            $table->unique(['parent_user_id', 'learner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_learner_links');
    }
};
