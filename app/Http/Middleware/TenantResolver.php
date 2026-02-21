<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\School;

class TenantResolver
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Resolve Tenant from Subdomain or Header
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        $school = School::where('slug', $subdomain)->first();

        if (!$school) {
            abort(404, 'School not found.');
        }

        // 2. Bind to Container
        app()->instance('current_school', $school);
        app()->instance('current_school_id', $school->id);

        // 3. Configure Timezone/Locale
        config(['app.timezone' => $school->timezone]);
        config(['app.locale' => $school->locale]);

        return $next($request);
    }
}
