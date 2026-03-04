<?php

// routes/api.php

use App\Http\Controllers\DatasetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

  /*
    |--------------------------------------------------------------------------
    | Datasets
    |--------------------------------------------------------------------------
    */
  Route::apiResource('datasets', DatasetController::class);
});
