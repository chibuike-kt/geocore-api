<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE datasets (
                id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name        VARCHAR(255) NOT NULL,
                slug        VARCHAR(255) NOT NULL,
                description TEXT,
                type        VARCHAR(50)  NOT NULL,
                srid        INTEGER      NOT NULL DEFAULT 4326,
                metadata    JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

                CONSTRAINT uq_datasets_slug UNIQUE (slug)
            )
        ');

    DB::statement('CREATE INDEX idx_datasets_type ON datasets (type)');
    DB::statement('CREATE INDEX idx_datasets_slug ON datasets (slug)');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS datasets CASCADE');
  }
};
