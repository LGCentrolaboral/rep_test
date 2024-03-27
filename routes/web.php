<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\ValidarMiembroController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/login/microsoft', [MicrosoftLoginController::class, 'redirectToMicrosoft']);
Route::get('/login/microsoft/callback', [MicrosoftLoginController::class, 'handleMicrosoftCallback']);

Route::post('/validarMiembro',[ValidarMiembroController::class,'validarMiembro']);


Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/miembrosTest',function(){
        return view('miembros.index');
    })->name('miembrosTest');
});
