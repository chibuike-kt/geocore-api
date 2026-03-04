<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE telemetry (
                id          BIGSERIAL    PRIMARY KEY,
                mission_id  UUID         NOT NULL REFERENCES missions(id) ON DELETE CASCADE,
                recorded_at TIMESTAMPTZ  NOT NULL,
                position    GEOMETRY(PointZ, 4326) NOT NULL,
                altitude    DOUBLE PRECISION,
                speed       DOUBLE PRECISION,
                heading     DOUBLE PRECISION,
                metadata    JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ');

    // Composite index: always query telemetry by mission + time window
    DB::statement('
            CREATE INDEX idx_telemetry_mission_time
            ON telemetry (mission_id, recorded_at)
        ');

    // Spatial index for proximity queries against telemetry points
    DB::statement('
            CREATE INDEX idx_telemetry_position
            ON telemetry
            USING GIST (position)
        ');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS telemetry CASCADE');
  }
};
