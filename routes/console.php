<?php

use App\Services\TailoredCvRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cv:prune {--days=30 : Delete tailored CVs older than this many days}', function (TailoredCvRepository $results) {
    $removed = $results->prune((int) $this->option('days'));
    $this->info("Deleted {$removed} tailored CV(s).");
})->purpose('Delete old tailored CV results, which contain personal data');

Schedule::command('cv:prune')->daily();
