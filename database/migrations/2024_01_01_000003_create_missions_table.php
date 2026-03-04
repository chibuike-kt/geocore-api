<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE missions (
                id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name         VARCHAR(255) NOT NULL,
                operator     VARCHAR(255) NOT NULL,
                status       VARCHAR(50)  NOT NULL DEFAULT \'planned\'
                                 CHECK (status IN (\'planned\',\'active\',\'completed\',\'aborted\')),
                start_time   TIMESTAMPTZ,
                end_time     TIMESTAMPTZ,
                planned_area GEOMETRY(Polygon, 4326),
                metadata     JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ');

    DB::statement('CREATE INDEX idx_missions_status       ON missions (status)');
    DB::statement('CREATE INDEX idx_missions_operator     ON missions (operator)');
    DB::statement('
            CREATE INDEX idx_missions_planned_area
            ON missions
            USING GIST (planned_area)
        ');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS missions CASCADE');
  }
};
