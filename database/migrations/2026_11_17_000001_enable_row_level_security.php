<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes tables in the public schema through its Data API. The app connects to Postgres
 * directly as the table owner, which RLS does not restrict, so enabling RLS with no policies blocks
 * API access (anon/authenticated roles) without affecting the app.
 */
return new class extends Migration
{
    private const TABLES = [
        'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'migrations', 'tailored_cvs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE IF EXISTS \"{$table}\" ENABLE ROW LEVEL SECURITY");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE IF EXISTS \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }
};
