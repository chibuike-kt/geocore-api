<?php

// routes/api.php

use App\Http\Controllers\DatasetController;
use App\Http\Controllers\FeatureController;
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
    | Note: bulk route must be defined BEFORE the resource route
    | to prevent {feature} catching "bulk" as a parameter
    |--------------------------------------------------------------------------
    */
  Route::post('datasets/{datasetId}/features/bulk', [FeatureController::class, 'bulk']);
  Route::get('datasets/{datasetId}/features',       [FeatureController::class, 'index']);
  Route::post('datasets/{datasetId}/features',      [FeatureController::class, 'store']);
});
