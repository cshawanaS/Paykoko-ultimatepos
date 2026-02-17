<?php

use Illuminate\Http\Request;

Route::middleware('auth:api')->get('/koko', function (Request $request) {
    return $request->user();
});
