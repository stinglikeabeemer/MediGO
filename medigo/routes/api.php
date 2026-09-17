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
use App\Http\Controllers\DiscussionReportController;
use App\Http\Middleware\AdminMiddleware;

// Rute Publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/faskes', [FaskesController::class, 'index']);
Route::get('/faskes/{id}', [FaskesController::class, 'show']);
Route::get('/faskes/{faskes_id}/comments', [FaskesCommentController::class, 'index']);
Route::post('/faskes', [FaskesController::class, 'store']);
Route::put('/faskes/{id}', [FaskesController::class, 'update']);
Route::delete('/faskes/{id}', [FaskesController::class, 'destroy']);
Route::get('/admin/stats', [FaskesController::class, 'getAdminStats']);

// Rute Forum Diskusi (Public)
Route::get('/discussions', [DiscussionController::class, 'index']); // Lihat semua diskusi
Route::get('/discussions/{id}', [DiscussionController::class, 'show']); // Lihat 1 diskusi spesifik

// Rute Terproteksi (Wajib punya Token / udah Login)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Cek profil user yang lagi login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/user/discussions', [DiscussionController::class, 'myDiscussions']);
    Route::get('/user/comments', [DiscussionController::class, 'myComments']);

    // Rute Bookmark (Wajib bawa Token)
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/faskes/{faskes_id}/bookmark', [BookmarkController::class, 'toggleBookmark']);

    // Rute Kasih Rating & Review ke Faskes (CRUD)
    Route::post('/faskes/{faskes_id}/comments', [FaskesCommentController::class, 'store']);
    Route::put('/comments/{id}', [FaskesCommentController::class, 'update']);
    Route::delete('/comments/{id}', [FaskesCommentController::class, 'destroy']);
    Route::get('/user/reviews', [FaskesCommentController::class, 'getUserReviews']);

    // Rute Forum Diskusi (Protected)
    Route::post('/discussions', [DiscussionController::class, 'store']); // Bikin diskusi baru

    // Rute Tambah Komentar di Diskusi
    Route::post('/discussions/{discussion_id}/comments', [DiscussionCommentController::class, 'store']);
    // Route untuk melaporkan komentar
    Route::post('/comments/{id}/report', [DiscussionCommentController::class, 'report']);
    Route::delete('/discussion-comments/{id}', [DiscussionCommentController::class, 'destroy']);
    // Rute Report Diskusi
    Route::post('/discussions/{id}/report', [DiscussionReportController::class, 'store']);

    // Rute Admin Dashboard Log (Wajib Login + Wajib Admin)
    Route::middleware(AdminMiddleware::class)->group(function () {
        Route::get('/admin/logs', [ActivityLogController::class, 'index']);
        Route::get('/admin/discussion-reports', [DiscussionReportController::class, 'indexAdmin']);
        Route::post('/admin/discussions/{id}/takedown', [DiscussionReportController::class, 'takedown']);
    });
});