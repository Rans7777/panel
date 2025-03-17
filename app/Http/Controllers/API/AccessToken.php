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
}
