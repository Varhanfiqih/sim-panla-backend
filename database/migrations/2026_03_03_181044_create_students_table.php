<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nisn')->unique();
            $table->string('nis')->nullable();
            $table->string('class_id'); // e.g., '7A', '9G'
            $table->string('name');
            $table->enum('gender', ['L', 'P']);
            $table->string('qr_code')->nullable()->unique();
            
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
