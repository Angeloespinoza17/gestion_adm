<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicCommunityOrganizationController;
use App\Http\Controllers\PublicContactMessageController;
use App\Http\Controllers\PublicEducationalProjectController;
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicSiteContentMediaController;
use App\Http\Controllers\PublicSiteEventController;
use App\Http\Controllers\PublicSiteInstallationController;
use App\Http\Controllers\PublicStudentLifeController;
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
Route::get('/proyecto-educativo', [PublicEducationalProjectController::class, 'show'])->name('public.educational-project');
Route::get('/proyecto-educativo/descargar', [PublicEducationalProjectController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('public.educational-project.download');
Route::view('/admision', 'public.pages.admissions')->name('public.admissions');
Route::get('/equipo', [PublicFacultyController::class, 'index'])->name('public.faculty');
Route::get('/instalaciones', [PublicSiteInstallationController::class, 'index'])->name('public.campus');
Route::get('/instalaciones/{siteInstallation}/portada', [PublicSiteContentMediaController::class, 'installationCover'])
    ->whereNumber('siteInstallation')
    ->middleware('throttle:120,1')
    ->name('public.installations.cover');
Route::get('/instalaciones/{siteInstallation}/galeria/{siteInstallationImage}', [PublicSiteContentMediaController::class, 'installationGallery'])
    ->whereNumber(['siteInstallation', 'siteInstallationImage'])
    ->middleware('throttle:120,1')
    ->name('public.installations.gallery');
Route::get('/cgpa', [PublicCommunityOrganizationController::class, 'cgpa'])->name('public.cgpa');
Route::get('/centro-de-estudiantes', [PublicCommunityOrganizationController::class, 'cde'])->name('public.cde');
Route::get('/comite-paritario', [PublicCommunityOrganizationController::class, 'jointCommittee'])->name('public.joint-committee');
Route::get('/vidaestudiantil', [PublicStudentLifeController::class, 'index'])->name('public.students-life');
Route::get('/vidaestudiantil/{studentLifePost}/portada', [PublicSiteContentMediaController::class, 'studentLifeCover'])
    ->whereNumber('studentLifePost')
    ->middleware('throttle:120,1')
    ->name('public.student-life.cover');
Route::get('/vidaestudiantil/{studentLifePost}/galeria/{studentLifePostImage}', [PublicSiteContentMediaController::class, 'studentLifeGallery'])
    ->whereNumber(['studentLifePost', 'studentLifePostImage'])
    ->middleware('throttle:120,1')
    ->name('public.student-life.gallery');
Route::get('/vidaestudiantil/{slug}', [PublicStudentLifeController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('public.student-life.show');
Route::redirect('/vida-estudiantil', '/vidaestudiantil');
Route::get('/noticias', [PublicNewsController::class, 'index'])->name('public.news');
Route::get('/noticias/imagen/{newsPost}', [PublicNewsController::class, 'image'])->whereNumber('newsPost')->name('public.news.image');
Route::get('/noticias/{newsPost}', [PublicNewsController::class, 'show'])->whereNumber('newsPost')->name('public.news.show');
Route::get('/eventos', [PublicSiteEventController::class, 'index'])->name('public.events');
Route::get('/eventos/{siteEvent}', [PublicSiteEventController::class, 'show'])->whereNumber('siteEvent')->name('public.events.show');
Route::get('/testimonios/{testimonial}/imagen', [PublicSiteContentMediaController::class, 'testimonialImage'])
    ->whereNumber('testimonial')
    ->middleware('throttle:120,1')
    ->name('public.testimonials.image');
Route::view('/contacto', 'public.pages.contact')->name('public.contact');
Route::post('/contacto', [PublicContactMessageController::class, 'store'])->middleware('throttle:6,1')->name('public.contact.store');

Route::get('/{any}', [HomeController::class, 'show'])->where('any', '^(?!api\/)[\/\w\.-]*');
