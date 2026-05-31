<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\S3FileController;

Route::middleware('token.auth')->group(function () {
    Route::post('/upload', [S3FileController::class, 'upload']);
    Route::get('/download', [S3FileController::class, 'download']);
    Route::delete('/delete', [S3FileController::class, 'delete']);
    Route::get('/list', [S3FileController::class, 'list']);
    Route::get('/credentials', [S3FileController::class, 'getTemporaryCredentials']);
});
