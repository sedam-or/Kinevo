<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE notes ADD COLUMN IF NOT EXISTS search_vector tsvector");

            DB::statement("CREATE INDEX IF NOT EXISTS notes_search_idx ON notes USING GIN (search_vector)");

            DB::statement("CREATE OR REPLACE FUNCTION notes_search_trigger() RETURNS trigger AS $$
BEGIN
    NEW.search_vector := to_tsvector('english', COALESCE(NEW.title, '') || ' ' || COALESCE(NEW.plain_text_cache, ''));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql");

            DB::statement("DROP TRIGGER IF EXISTS notes_search_update ON notes");

            DB::statement("CREATE TRIGGER notes_search_update BEFORE INSERT OR UPDATE ON notes
    FOR EACH ROW EXECUTE FUNCTION notes_search_trigger()");

            DB::statement("UPDATE notes SET search_vector = to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(plain_text_cache, ''))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("DROP TRIGGER IF EXISTS notes_search_update ON notes");
            DB::statement("DROP FUNCTION IF EXISTS notes_search_trigger()");
            DB::statement("DROP INDEX IF EXISTS notes_search_idx");
            DB::statement("ALTER TABLE notes DROP COLUMN IF EXISTS search_vector");
        }
    }
};
