<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenders:send-reminders')->hourly();
Schedule::command('collector:daily')->everyFiveMinutes()->withoutOverlapping();
