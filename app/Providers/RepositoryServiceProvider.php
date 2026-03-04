<?php
// app/Providers/RepositoryServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\FeatureRepositoryInterface;
use App\Repositories\Contracts\MissionRepositoryInterface;
use App\Repositories\Contracts\TelemetryRepositoryInterface;
use App\Repositories\Contracts\GeofenceRepositoryInterface;

use App\Repositories\DatasetRepository;
use App\Repositories\FeatureRepository;
use App\Repositories\MissionRepository;
use App\Repositories\TelemetryRepository;
use App\Repositories\GeofenceRepository;

class RepositoryServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->bind(DatasetRepositoryInterface::class,   DatasetRepository::class);
    $this->app->bind(FeatureRepositoryInterface::class,   FeatureRepository::class);
    $this->app->bind(MissionRepositoryInterface::class,   MissionRepository::class);
    $this->app->bind(TelemetryRepositoryInterface::class, TelemetryRepository::class);
    $this->app->bind(GeofenceRepositoryInterface::class,  GeofenceRepository::class);
  }
}
