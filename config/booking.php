<?php

/*
|--------------------------------------------------------------------------
| Booking System
|--------------------------------------------------------------------------
| Tunables for the booking + scheduling subsystem. Production deployments
| can override every value via env() without touching this file.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Availability rule cache
    |--------------------------------------------------------------------------
    | TTL (seconds) for the cached PhotographerAvailability rule set. Each
    | rule save/delete busts the photographer's key, so the TTL is the
    | upper bound on stale-rule windows when admin edits via raw SQL or
    | another process. 10 minutes is a comfortable balance between
    | freshness and read amplification on the photographer_availability
    | table during high-traffic booking-form loads.
    */
    'availability_cache_ttl' => (int) env('BOOKING_AVAILABILITY_CACHE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Recurring booking materializer
    |--------------------------------------------------------------------------
    | The MaterializeBookingSeriesJob runs daily and creates concrete
    | Booking rows up to `materialize_horizon_days` ahead of "now".
    | Default 90 days — far enough that a customer who books weekly for
    | the next quarter sees their full schedule, but not so far that we
    | accumulate millions of pre-created bookings nobody will keep.
    */
    'materialize_horizon_days' => (int) env('BOOKING_MATERIALIZE_HORIZON_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Google Calendar Watch
    |--------------------------------------------------------------------------
    | Google's events.watch channels expire after 7 days max. We renew at
    | `gcal_watch_renew_hours` before expiry to avoid edge-of-window
    | misses (network blips around the renewal window). Default 12 hours
    | of headroom = safe even if the cron runs only once a day.
    */
    'gcal_watch_renew_hours' => (int) env('BOOKING_GCAL_WATCH_RENEW_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | Default booking timezone
    |--------------------------------------------------------------------------
    | When a booking is created without an explicit timezone (e.g. legacy
    | code paths that pre-date the timezone column), this is the fallback
    | used for display + reminder formatting. Choose the timezone where
    | most of your photographers operate.
    */
    'default_timezone' => env('BOOKING_DEFAULT_TZ', config('app.timezone', 'Asia/Bangkok')),

];
