<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Cek apakah user yang login punya role 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request); // Silakan masuk
        }

        // Kalau bukan admin, tendang keluar pakai error 403
        return response()->json([
            'message' => 'Akses ditolak. Rute ini khusus Admin Medigo.'
        ], 403);
    }}