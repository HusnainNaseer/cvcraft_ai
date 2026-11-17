<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tailored_cvs', function (Blueprint $table) {
            // Unguessable token used in preview, download and share links.
            $table->string('token', 40)->primary();
            $table->json('cv');
            $table->json('job');
            $table->timestamps();

            // cv:prune deletes by last update.
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tailored_cvs');
    }
};
