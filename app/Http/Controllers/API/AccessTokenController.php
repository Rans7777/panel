<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessTokenController extends Controller
{
    public function index()
    {
        $accessToken = str_replace('-', '', Str::uuid()->toString());
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
                'valid' => true,
            ]);
        }

        return response()->json([
            'valid' => false,
        ], 401);
    }
}
