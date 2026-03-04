<?php

// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Events\GeofenceEntered;
use App\Events\GeofenceExited;
use App\Listeners\LogGeofenceEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register geofence event listeners
        Event::listen(GeofenceEntered::class, LogGeofenceEvent::class);
        Event::listen(GeofenceExited::class,  LogGeofenceEvent::class);
    }
}
