<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE audit_logs (
                id          BIGSERIAL    PRIMARY KEY,
                entity_type VARCHAR(100) NOT NULL,
                entity_id   VARCHAR(255) NOT NULL,
                action      VARCHAR(50)  NOT NULL,
                payload     JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                ip_address  VARCHAR(45),
                user_agent  TEXT,
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ');

    DB::statement('CREATE INDEX idx_audit_entity  ON audit_logs (entity_type, entity_id)');
    DB::statement('CREATE INDEX idx_audit_action  ON audit_logs (action)');
    DB::statement('CREATE INDEX idx_audit_created ON audit_logs (created_at DESC)');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS audit_logs CASCADE');
  }
};
