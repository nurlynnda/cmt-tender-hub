<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenders:send-reminders')->hourly();
