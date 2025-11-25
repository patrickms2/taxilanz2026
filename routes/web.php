<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('taxis', App\Http\Controllers\TaxiController::class);
Route::resource('departamentos', App\Http\Controllers\DepartamentoController::class);
Route::resource('consultas', App\Http\Controllers\ConsultaController::class);
Route::resource('documentos', App\Http\Controllers\DocumentoController::class);
Route::resource('citas', App\Http\Controllers\CitaController::class);
Route::resource('localizacion_taxi', App\Http\Controllers\LocalizacionTaxiController::class);
Route::resource('localizacion_historico', App\Http\Controllers\LocalizacionHistoricoController::class);
Route::get('/api/taxis/locations', [App\Http\Controllers\LocalizacionTaxiController::class, 'getLocations']);

