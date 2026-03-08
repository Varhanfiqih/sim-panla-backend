<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            // Using string for ID because it's '7A', '7B', etc.
            $table->string('id')->primary();
            $table->string('name');
            $table->string('homeroom_teacher_id')->nullable();
            
            // Add foreign key pointing to users table nip
            $table->foreign('homeroom_teacher_id')->references('nip')->on('users')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
