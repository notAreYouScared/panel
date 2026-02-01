<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('credential_id', 500)->unique();
            $table->text('public_key_data');
            $table->string('aaguid')->nullable();
            $table->string('attestation_type')->nullable();
            $table->json('transports')->nullable();
            $table->unsignedBigInteger('counter')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'credential_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
