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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('avatar')->default('demo/default.png');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            
            $table->string('username')->unique();
            $table->char('language', 7)->default('en');
            $table->dateTime('trial_ends_at')->nullable();
            $table->string('verification_code')->nullable();
            $table->tinyInteger('verified')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
