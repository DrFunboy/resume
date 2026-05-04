<?php

use App\Http\Controllers\Amo2Sheets;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return 'Test';
});

Route::prefix('/sheets')->group(function (){
    Route::get('/install', [Amo2Sheets::class, 'install'])->name('amoInstall');;
    Route::get('/uninstall', [Amo2Sheets::class, 'uninstall']);

    Route::middleware('HasDomain')->group(function () {
        Route::get('/connections', [Amo2Sheets::class, 'connections']);
        Route::post('/connection', [Amo2Sheets::class, 'storeConnection']);
        Route::delete('/connection', [Amo2Sheets::class, 'deleteConnection']);

        Route::post('/sync-connection', [Amo2Sheets::class, 'syncConnection']);

        Route::get('/filters', [Amo2Sheets::class, 'filters']);
        Route::post('/filter', [Amo2Sheets::class, 'storeFilter']);
        Route::delete('/filter', [Amo2Sheets::class, 'deleteFilter']);

        Route::get('/full-data', [Amo2Sheets::class, 'fullData']);

        Route::get('/oauth-check', [Amo2Sheets::class, 'oauthCheck']);
        Route::post('/oauth-logout', [Amo2Sheets::class, 'oauthLogout']);
    });
    Route::get('/oauth', [Amo2Sheets::class, 'oauthComplete']);

    Route::post('/event', [Amo2Sheets::class, 'addEvent']);
});
