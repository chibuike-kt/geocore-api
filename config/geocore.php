<?php
// config/geocore.php

return [

  /*
    |--------------------------------------------------------------------------
    | Default Spatial Reference System
    |--------------------------------------------------------------------------
    | All geometries in GeoCore use WGS84 (EPSG:4326).
    | Do not change this unless you fully understand the implications.
    */
  'default_srid' => env('GEOCORE_DEFAULT_SRID', 4326),

  /*
    |--------------------------------------------------------------------------
    | Bulk Ingestion Limits
    |--------------------------------------------------------------------------
    */
  'max_bulk_features' => env('GEOCORE_MAX_BULK_FEATURES', 1000),

  /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */
  'export' => [
    'disk'   => env('GEOCORE_EXPORT_DISK', 'local'),
    'path'   => 'exports',
    'ttl'    => 3600, // seconds before export file is purged
  ],

  /*
    |--------------------------------------------------------------------------
    | Spatial Query Defaults
    |--------------------------------------------------------------------------
    */
  'query' => [
    'max_radius_meters' => 50000,   // 50km hard cap on radius queries
    'default_limit'     => 100,
    'max_limit'         => 1000,
  ],

  /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    */
  'telemetry' => [
    'batch_size' => 500,            // max pings per single ingest request
  ],

];
