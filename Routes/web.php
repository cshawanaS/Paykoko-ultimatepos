<?php

Route::group(['middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'koko'], function () {
    Route::get('/settings', 'KokoController@index')->name('koko.settings');
    Route::post('/settings', 'KokoController@updateSettings')->name('koko.update_settings');

    // Install routes
    Route::get('/install', 'InstallController@index');
    Route::get('/update', 'InstallController@update');
    Route::get('/uninstall', 'InstallController@uninstall');
});

// Guest-safe return route
Route::group(['middleware' => ['web', 'language'], 'prefix' => 'koko'], function () {
    Route::any('/return/{id}', 'KokoController@paymentReturn')->name('koko.return');
});

// Koko Webhook
Route::post('/webhook/koko/notify', 'KokoController@notify')->name('koko.notify');
