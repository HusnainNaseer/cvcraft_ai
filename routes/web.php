<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\JobDescriptionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');

// Fresh CSRF token for pages left open longer than the session lifetime.
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])
    ->header('Cache-Control', 'no-store'))->name('csrf.refresh');

// Job description
Route::post('/job-description/fetch', [JobDescriptionController::class, 'fetch'])
    ->middleware('throttle:15,1')->name('job-description.fetch');
Route::post('/job-description/skills', [JobDescriptionController::class, 'skills'])
    ->middleware('throttle:60,1')->name('job-description.skills');

// Tailored CV
Route::post('/cv/tailor', [CvController::class, 'tailor'])
    ->middleware('throttle:10,1')->name('cv.tailor');
Route::post('/cv/fact-check', [CvController::class, 'factCheck'])
    ->middleware('throttle:20,1')->name('cv.fact-check');
Route::post('/cv/photo', [CvController::class, 'updatePhoto'])->name('cv.photo');
Route::post('/cv/theme', [CvController::class, 'updateTheme'])->name('cv.theme');
Route::get('/cv/content', [CvController::class, 'content'])->name('cv.content');
Route::post('/cv/content', [CvController::class, 'updateContent'])->middleware('throttle:60,1')->name('cv.content.update');
Route::get('/cv/download', [DownloadController::class, 'cv'])->name('cv.download');
Route::get('/cv/share', [DownloadController::class, 'share'])->name('cv.share');

// Applying
Route::post('/application/prepare', [ApplicationController::class, 'prepare'])
    ->middleware('throttle:20,1')->name('application.prepare');
Route::post('/application/email', [ApplicationController::class, 'sendEmail'])
    ->middleware('throttle:5,1')->name('application.email');

// Gmail connection
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::post('/auth/google/disconnect', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
