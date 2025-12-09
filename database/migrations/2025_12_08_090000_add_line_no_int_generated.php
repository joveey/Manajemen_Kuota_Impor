<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ---------- po_lines ----------
        if (Schema::hasTable('po_lines')) {

            if (! Schema::hasColumn('po_lines', 'line_no_int')) {

                if ($driver === 'sqlsrv') {
                    // Versi aman untuk SQL Server lama, tanpa TRY_CONVERT
                    DB::statement("
                        ALTER TABLE po_lines
                        ADD line_no_int AS (
                            CASE
                                WHEN NULLIF(LTRIM(RTRIM(line_no)), '') IS NULL THEN NULL
                                WHEN LTRIM(RTRIM(line_no)) NOT LIKE '%[^0-9]%' THEN CAST(LTRIM(RTRIM(line_no)) AS int)
                                ELSE NULL
                            END
                        ) PERSISTED
                    ");
                } else {
                    DB::statement("
                        ALTER TABLE po_lines
                        ADD COLUMN line_no_int int GENERATED ALWAYS AS
                        (
                            NULLIF(
                                regexp_replace(COALESCE(line_no,''),'[^0-9]','','g'),
                                ''
                            )::int
                        ) STORED
                    ");
                }
            }

            if ($driver === 'sqlsrv') {
                DB::statement("
                    IF NOT EXISTS (
                        SELECT 1
                        FROM sys.indexes
                        WHERE name = 'po_lines_po_header_id_line_no_int_idx'
                          AND object_id = OBJECT_ID('po_lines')
                    )
                    CREATE INDEX po_lines_po_header_id_line_no_int_idx
                    ON po_lines (po_header_id, line_no_int)
                ");
            } else {
                DB::statement("
                    CREATE INDEX IF NOT EXISTS po_lines_po_header_id_line_no_int_idx
                    ON po_lines (po_header_id, line_no_int)
                ");
            }
        }

        // ---------- gr_receipts ----------
        if (Schema::hasTable('gr_receipts')) {

            if (! Schema::hasColumn('gr_receipts', 'line_no_int')) {

                if ($driver === 'sqlsrv') {
                    // Versi aman untuk SQL Server lama, tanpa TRY_CONVERT
                    DB::statement("
                        ALTER TABLE gr_receipts
                        ADD line_no_int AS (
                            CASE
                                WHEN NULLIF(LTRIM(RTRIM(line_no)), '') IS NULL THEN NULL
                                WHEN LTRIM(RTRIM(line_no)) NOT LIKE '%[^0-9]%' THEN CAST(LTRIM(RTRIM(line_no)) AS int)
                                ELSE NULL
                            END
                        ) PERSISTED
                    ");
                } else {
                    DB::statement("
                        ALTER TABLE gr_receipts
                        ADD COLUMN line_no_int int GENERATED ALWAYS AS
                        (
                            NULLIF(
                                regexp_replace(CAST(line_no as text),'[^0-9]','','g'),
                                ''
                            )::int
                        ) STORED
                    ");
                }
            }

            if ($driver === 'sqlsrv') {
                DB::statement("
                    IF NOT EXISTS (
                        SELECT 1
                        FROM sys.indexes
                        WHERE name = 'gr_receipts_po_no_line_no_int_idx'
                          AND object_id = OBJECT_ID('gr_receipts')
                    )
                    CREATE INDEX gr_receipts_po_no_line_no_int_idx
                    ON gr_receipts (po_no, line_no_int)
                ");
            } else {
                DB::statement("
                    CREATE INDEX IF NOT EXISTS gr_receipts_po_no_line_no_int_idx
                    ON gr_receipts (po_no, line_no_int)
                ");
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (Schema::hasTable('po_lines') && Schema::hasColumn('po_lines', 'line_no_int')) {

            if ($driver === 'sqlsrv') {
                DB::statement("
                    IF EXISTS (
                        SELECT 1
                        FROM sys.indexes
                        WHERE name = 'po_lines_po_header_id_line_no_int_idx'
                          AND object_id = OBJECT_ID('po_lines')
                    )
                    DROP INDEX po_lines_po_header_id_line_no_int_idx ON po_lines
                ");
            } else {
                DB::statement("DROP INDEX IF EXISTS po_lines_po_header_id_line_no_int_idx");
            }

            DB::statement("ALTER TABLE po_lines DROP COLUMN line_no_int");
        }

        if (Schema::hasTable('gr_receipts') && Schema::hasColumn('gr_receipts', 'line_no_int')) {

            if ($driver === 'sqlsrv') {
                DB::statement("
                    IF EXISTS (
                        SELECT 1
                        FROM sys.indexes
                        WHERE name = 'gr_receipts_po_no_line_no_int_idx'
                          AND object_id = OBJECT_ID('gr_receipts')
                    )
                    DROP INDEX gr_receipts_po_no_line_no_int_idx ON gr_receipts
                ");
            } else {
                DB::statement("DROP INDEX IF EXISTS gr_receipts_po_no_line_no_int_idx");
            }

            DB::statement("ALTER TABLE gr_receipts DROP COLUMN line_no_int");
        }
    }
};
