<?php

Route::group(['middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'koko'], function () {
    Route::get('/settings', 'KokoController@index')->name('koko.settings');
    Route::post('/settings', 'KokoController@updateSettings')->name('koko.update_settings');

    // Install routes
    Route::get('/install', 'InstallController@index');
    Route::get('/update', 'InstallController@update');
    Route::get('/uninstall', 'InstallController@uninstall');
});

// Guest-safe return route (Remains for secondary uses/backward compatibility if needed, but primary is the callback)
Route::group(['middleware' => ['web', 'language'], 'prefix' => 'koko'], function () {
    Route::any('/return/{id}', 'KokoController@paymentReturn')->name('koko.return');
});

// Unified Koko Callback (Safe from CSRF via /webhook/* exclusion)
Route::any('/webhook/koko/callback/{id}', 'KokoController@handleCallback')->name('koko.callback');

// Status Landing Page
Route::get('/koko/status/{id}', 'KokoController@statusPage')->name('koko.status');

// Deprecated notification route (Keeping temporary for safe migration if any cached forms exist)
Route::post('/webhook/koko/notify', 'KokoController@notify')->name('koko.notify');
