<?php

// routes/api.php

use App\Http\Controllers\DatasetController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\QueryController;
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
    | bulk must be defined before the general store route
    |--------------------------------------------------------------------------
    */
  Route::post('datasets/{datasetId}/features/bulk', [FeatureController::class, 'bulk']);
  Route::get('datasets/{datasetId}/features',       [FeatureController::class, 'index']);
  Route::post('datasets/{datasetId}/features',      [FeatureController::class, 'store']);

  /*
    |--------------------------------------------------------------------------
    | Spatial Queries
    | within and intersects use POST because they accept a geometry body.
    | radius and nearest use GET because they only need query parameters.
    |--------------------------------------------------------------------------
    */
  Route::prefix('query')->group(function () {
    Route::post('within',      [QueryController::class, 'within']);
    Route::get('radius',       [QueryController::class, 'radius']);
    Route::get('nearest',      [QueryController::class, 'nearest']);
    Route::post('intersects',  [QueryController::class, 'intersects']);
  });
});
