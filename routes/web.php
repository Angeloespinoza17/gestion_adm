<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


// Auth::routes();

Route::view('/', 'public.home')->name('public.home');
Route::view('/nosotros', 'public.pages.about')->name('public.about');
Route::view('/admision', 'public.pages.admissions')->name('public.admissions');
Route::view('/equipo', 'public.pages.faculty')->name('public.faculty');
Route::view('/instalaciones', 'public.pages.campus')->name('public.campus');
Route::view('/vidaestudiantil', 'public.pages.students-life')->name('public.students-life');
Route::redirect('/vida-estudiantil', '/vidaestudiantil');
Route::view('/noticias', 'public.pages.news')->name('public.news');
Route::view('/eventos', 'public.pages.events')->name('public.events');
Route::view('/contacto', 'public.pages.contact')->name('public.contact');

Route::get('/{any}', [HomeController::class, 'show'])->where('any', '^(?!api\/)[\/\w\.-]*');
