<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\DeleteExpiredTokens;

Schedule::job(DeleteExpiredTokens::class)->everyFiveMinutes();
