<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicContactMessageController;
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicSiteEventController;
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

Route::get('/', [PublicNewsController::class, 'home'])->name('public.home');
Route::view('/nosotros', 'public.pages.about')->name('public.about');
Route::view('/admision', 'public.pages.admissions')->name('public.admissions');
Route::get('/equipo', [PublicFacultyController::class, 'index'])->name('public.faculty');
Route::view('/instalaciones', 'public.pages.campus')->name('public.campus');
Route::view('/vidaestudiantil', 'public.pages.students-life')->name('public.students-life');
Route::redirect('/vida-estudiantil', '/vidaestudiantil');
Route::get('/noticias', [PublicNewsController::class, 'index'])->name('public.news');
Route::get('/noticias/imagen/{newsPost}', [PublicNewsController::class, 'image'])->whereNumber('newsPost')->name('public.news.image');
Route::get('/noticias/{newsPost}', [PublicNewsController::class, 'show'])->whereNumber('newsPost')->name('public.news.show');
Route::get('/eventos', [PublicSiteEventController::class, 'index'])->name('public.events');
Route::get('/eventos/{siteEvent}', [PublicSiteEventController::class, 'show'])->whereNumber('siteEvent')->name('public.events.show');
Route::view('/contacto', 'public.pages.contact')->name('public.contact');
Route::post('/contacto', [PublicContactMessageController::class, 'store'])->middleware('throttle:6,1')->name('public.contact.store');

Route::get('/{any}', [HomeController::class, 'show'])->where('any', '^(?!api\/)[\/\w\.-]*');
