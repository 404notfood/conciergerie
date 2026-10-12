<?php
use App\Http\Controllers\{ClientPortalController, DirectoryController, MissionController, TerrainController, WorkspaceController};
use Illuminate\Support\Facades\Route;
Route::inertia('/', 'welcome')->name('home');
Route::middleware(['auth','verified'])->group(function () {
    Route::get('/dashboard',[WorkspaceController::class,'index'])->name('dashboard');
    Route::post('/workspace',[WorkspaceController::class,'store'])->name('workspace.store');
    Route::post('/workspace/switch',[WorkspaceController::class,'switch'])->name('workspace.switch');
    Route::post('/clients',[DirectoryController::class,'client'])->name('clients.store');
    Route::post('/properties',[DirectoryController::class,'property'])->name('properties.store');
    Route::post('/members',[DirectoryController::class,'member'])->name('members.store');
    Route::post('/templates',[DirectoryController::class,'template'])->name('templates.store');
    Route::post('/stays',[DirectoryController::class,'stay'])->name('stays.store');
    Route::post('/stays/import',[DirectoryController::class,'import'])->name('stays.import');
    Route::post('/missions',[MissionController::class,'store'])->name('missions.store');
    Route::post('/missions/{mission}/transition',[MissionController::class,'transition'])->name('missions.transition');
    Route::post('/missions/{mission}/reports',[MissionController::class,'report'])->name('missions.report');
    Route::post('/reports/{report}/review',[MissionController::class,'review'])->name('reports.review');
    Route::post('/missions/{mission}/incidents',[MissionController::class,'incident'])->name('missions.incident');
    Route::post('/incidents/{incident}/share',[MissionController::class,'shareIncident'])->name('incidents.share');
    Route::get('/photos/{photo}',[MissionController::class,'photo'])->name('photos.show');
    Route::post('/messages',[ClientPortalController::class,'message'])->name('messages.store');
    Route::post('/requests',[ClientPortalController::class,'request'])->name('requests.store');
    Route::post('/requests/{interventionRequest}/decision',[ClientPortalController::class,'decide'])->name('requests.decide');
    Route::get('/terrain-data',[TerrainController::class,'data'])->name('terrain.data');
});
require __DIR__.'/settings.php';
