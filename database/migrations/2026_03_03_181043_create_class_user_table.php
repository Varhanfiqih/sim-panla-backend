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
        Schema::create('class_user', function (Blueprint $table) {
            $table->string('user_nip');
            $table->string('class_id');
            
            $table->foreign('user_nip')->references('nip')->on('users')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            
            $table->primary(['user_nip', 'class_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_user');
    }
};
