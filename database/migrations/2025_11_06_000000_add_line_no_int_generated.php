<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add generated columns and indexes if not present (PostgreSQL)
        if (Schema::hasTable('po_lines')) {
            if (! Schema::hasColumn('po_lines','line_no_int')) {
                DB::statement("ALTER TABLE po_lines ADD COLUMN line_no_int int GENERATED ALWAYS AS (NULLIF(regexp_replace(COALESCE(line_no,''),'[^0-9]','','g'),'')::int) STORED");
            }
            DB::statement("CREATE INDEX IF NOT EXISTS po_lines_po_header_id_line_no_int_idx ON po_lines (po_header_id, line_no_int)");
        }
        if (Schema::hasTable('gr_receipts')) {
            if (! Schema::hasColumn('gr_receipts','line_no_int')) {
                DB::statement("ALTER TABLE gr_receipts ADD COLUMN line_no_int int GENERATED ALWAYS AS (NULLIF(regexp_replace(CAST(line_no as text),'[^0-9]','','g'),'')::int) STORED");
            }
            DB::statement("CREATE INDEX IF NOT EXISTS gr_receipts_po_no_line_no_int_idx ON gr_receipts (po_no, line_no_int)");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('po_lines')) {
            if (Schema::hasColumn('po_lines','line_no_int')) {
                DB::statement("ALTER TABLE po_lines DROP COLUMN line_no_int");
            }
        }
        if (Schema::hasTable('gr_receipts')) {
            if (Schema::hasColumn('gr_receipts','line_no_int')) {
                DB::statement("ALTER TABLE gr_receipts DROP COLUMN line_no_int");
            }
        }
    }
};

