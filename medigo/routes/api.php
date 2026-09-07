<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FaskesController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\DiscussionCommentController;
use App\Http\Controllers\FaskesCommentController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Middleware\AdminMiddleware;

// Rute Publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/faskes', [FaskesController::class, 'index']);
Route::get('/faskes/{id}', [FaskesController::class, 'show']);
Route::post('/faskes', [FaskesController::class, 'store']);
Route::put('/faskes/{id}', [FaskesController::class, 'update']);
Route::delete('/faskes/{id}', [FaskesController::class, 'destroy']);
Route::get('/admin/stats', [FaskesController::class, 'getAdminStats']);

// Rute Terproteksi (Wajib punya Token / udah Login)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Cek profil user yang lagi login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rute Bookmark (Wajib bawa Token)
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/faskes/{faskes_id}/bookmark', [BookmarkController::class, 'toggleBookmark']);

    // Rute Kasih Rating & Review ke Faskes
    Route::post('/faskes/{faskes_id}/comments', [FaskesCommentController::class, 'store']);

    // Rute Forum Diskusi
    Route::get('/discussions', [DiscussionController::class, 'index']); // Lihat semua diskusi
    Route::post('/discussions', [DiscussionController::class, 'store']); // Bikin diskusi baru
    Route::get('/discussions/{id}', [DiscussionController::class, 'show']); // Lihat 1 diskusi spesifik

    // Rute Tambah Komentar di Diskusi
    Route::post('/discussions/{discussion_id}/comments', [DiscussionCommentController::class, 'store']);

    // Rute Admin Dashboard Log (Wajib Login + Wajib Admin)
    Route::get('/admin/logs', [ActivityLogController::class, 'index'])->middleware(AdminMiddleware::class);
});