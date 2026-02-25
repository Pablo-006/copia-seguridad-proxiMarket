<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Log;
use App\Models\User;

class LogController extends Controller
{

    public function mostrar() {

        return Log::all();
    }

    public function show(string $userId)
    {
        $user = User::findOrFail($userId);

        $logs = Log::where('user_id', $userId)->get();
        return response()->json($logs, 200);
    }
    
}
