<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('kafka:test-consume-start', function () {
    $this->call('kafka:cat-test-consume');
})->purpose('Deprecated alias — use kafka:cat-test-consume');

Artisan::command('kafka:lesson-consume-start', function () {
    $this->call('kafka:lesson-consume');
})->purpose('Alias for kafka:lesson-consume');

Artisan::command('kafka:code-review-consume-start', function () {
    $this->call('kafka:code-review-consume');
})->purpose('Alias for kafka:code-review-consume');

Artisan::command('kafka:hr-interview-consume-start', function () {
    $this->call('kafka:hr-interview-consume');
})->purpose('Alias for kafka:hr-interview-consume');
