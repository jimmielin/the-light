<?php

namespace App\Http\Middleware;

use Closure;

class BuildUserData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // $request->uData is already present. Populate with further field information
        // used.

        // Compute entitlements for various high level actions.
        $ring = $request->uData->ring;
        $request->uData->entitlements = [
            "EditingText" => $ring == 0, // 0
            "EditingTypeExtra" => $ring == 0, // 0

            "Tagging" => $ring < 2, // 0, 1

            "ViewingDeleted" => $ring < 2, // 0, 1
            "ViewingFlags" => $ring < 3, // 0, 1, 2
            "UndoBan" => $ring < 2, // 0, 1

            "CanPostBridge" => config('app.env') != "hkg3",

            "Sudoers" => $ring == 0,
        ];
        
        return $next($request);
    }
}
