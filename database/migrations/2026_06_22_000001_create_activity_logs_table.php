<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('IB_activity_logs', function (Blueprint $table) {
            $table->id();

            // User yang melakukan aktivitas
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 100)->nullable(); 
            $table->string('user_role', 50)->nullable();

            // Jenis aktivitas (ikuti gambar: Autentikasi, Buat Data, Hapus Data, dll)
            $table->string('jenis_aktivitas', 50);       
            $table->string('aktivitas', 255);             

            // Modul & subject (untuk filter dan detail)
            $table->string('module', 50)->nullable();   
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type', 100)->nullable();

            // Data tambahan (payload before/after jika diperlukan)
            $table->json('properties')->nullable();

            // Jaringan
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['jenis_aktivitas', 'created_at']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('IB_activity_logs');
    }
};
