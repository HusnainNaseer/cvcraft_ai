<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tailored_cvs', function (Blueprint $table) {
            // Text of the original uploaded CV: the only trusted source of facts for the application email.
            $table->longText('source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tailored_cvs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
