<?php

// routes/api.php

use App\Http\Controllers\DatasetController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

  /*
    |--------------------------------------------------------------------------
    | Datasets
    |--------------------------------------------------------------------------
    */
  Route::apiResource('datasets', DatasetController::class);

  /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
  Route::post('datasets/{datasetId}/features/bulk', [FeatureController::class, 'bulk']);
  Route::get('datasets/{datasetId}/features',       [FeatureController::class, 'index']);
  Route::post('datasets/{datasetId}/features',      [FeatureController::class, 'store']);

  /*
    |--------------------------------------------------------------------------
    | Spatial Queries
    |--------------------------------------------------------------------------
    */
  Route::prefix('query')->group(function () {
    Route::post('within',     [QueryController::class, 'within']);
    Route::get('radius',      [QueryController::class, 'radius']);
    Route::get('nearest',     [QueryController::class, 'nearest']);
    Route::post('intersects', [QueryController::class, 'intersects']);
  });

  /*
    |--------------------------------------------------------------------------
    | Missions
    |--------------------------------------------------------------------------
    */
  Route::apiResource('missions', MissionController::class);
  Route::patch('missions/{missionId}/status',    [MissionController::class, 'status']);
  Route::post('missions/{missionId}/telemetry',  [TelemetryController::class, 'store']);
  Route::get('missions/{missionId}/track',       [TelemetryController::class, 'track']);
});
