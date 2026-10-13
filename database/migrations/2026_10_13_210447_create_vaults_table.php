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
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('server_url')->nullable();
            $table->text('client_id');
            $table->text('client_secret');
            $table->text('master_password');
            $table->boolean('json_export_enabled')->default(false);
            $table->text('json_export_password')->nullable();
            $table->boolean('encrypted_json_export_enabled')->default(false);
            $table->text('encrypted_json_export_password')->nullable();
            $table->string('frequency');
            $table->unsignedSmallInteger('retain_backups_count');
            $table->dateTime('next_backup_at')->nullable()->index();
            $table->dateTime('last_backed_up_at')->nullable();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
