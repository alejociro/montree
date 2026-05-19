<?php

use App\Jobs\ExpirePendingBookingsJob;
use App\Jobs\SendBookingReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendBookingReminderJob)->hourly();
Schedule::job(new ExpirePendingBookingsJob)->hourly();
