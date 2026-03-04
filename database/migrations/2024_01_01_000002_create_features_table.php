<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE features (
                id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                dataset_id    UUID         NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
                name          VARCHAR(255),
                geometry_type VARCHAR(50)  NOT NULL,
                geometry      GEOMETRY(Geometry, 4326) NOT NULL,
                properties    JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                source_id     VARCHAR(255),
                hash          VARCHAR(64),
                created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

                CONSTRAINT uq_feature_source UNIQUE (dataset_id, source_id)
            )
        ');

    // GiST spatial index — this is what makes ST_Within, ST_Intersects fast
    DB::statement('
            CREATE INDEX idx_features_geometry
            ON features
            USING GIST (geometry)
        ');

    DB::statement('CREATE INDEX idx_features_dataset_id    ON features (dataset_id)');
    DB::statement('CREATE INDEX idx_features_geometry_type ON features (geometry_type)');
    DB::statement('CREATE INDEX idx_features_hash          ON features (hash)');
    DB::statement('CREATE INDEX idx_features_source_id     ON features (source_id)');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS features CASCADE');
  }
};
