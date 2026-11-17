<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pqrs', function (Blueprint $table) {
            $table->id();
            $table->string('radicado', 40)->unique();
            $table->string('type', 30)->index();
            $table->string('status', 30)->default('radicada')->index();
            $table->string('channel', 20)->default('web');

            $table->string('requester_name');
            $table->string('requester_document_type', 10)->nullable();
            $table->string('requester_document', 30)->nullable();
            $table->string('requester_email')->index();
            $table->string('requester_phone', 30)->nullable();

            $table->string('subject');
            $table->text('description');
            $table->json('extra_fields')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('pqrs_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pqrs_id')->constrained('pqrs')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('pqrs_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pqrs_id')->constrained('pqrs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->boolean('is_public')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pqrs_activities');
        Schema::dropIfExists('pqrs_attachments');
        Schema::dropIfExists('pqrs');
    }
};
