<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Weekly forum digest for members who opted in (profile → Editar perfil).
Schedule::command('forum:digest')->weeklyOn(1, '08:00');
