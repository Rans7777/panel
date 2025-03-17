<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccessToken extends Controller
{
    public function index()
    {
        $accessToken = str_replace('-', '', Str::uuid());
        $createdAt = Carbon::now(config('app.timezone'));
        DB::table('access_tokens')->insert([
            'access_token' => $accessToken,
            'created_at' => $createdAt,
        ]);
        return response()->json(['access_token' => $accessToken]);
    }

    public function get()
    {
        $accessToken = DB::table('access_tokens')->orderBy('created_at', 'desc')->first();
        return response()->json(['access_token' => $accessToken->access_token]);
    }

    public function expiry($token)
    {
        $currentTime = Carbon::now(config('app.timezone'));
        $validTime = $currentTime->copy()->subMinutes(5);
        $accessToken = DB::table('access_tokens')
            ->where('access_token', $token)
            ->where('created_at', '>=', $validTime)
            ->first();
        if ($accessToken) {
            return response()->json([
                'valid' => true
            ]);
        }
        return response()->json([
            'valid' => false,
        ], 401);
    }
}
