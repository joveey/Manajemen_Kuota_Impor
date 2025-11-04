<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Avoid logging the audit log listing itself to prevent noise/loops
        $routeName = optional($request->route())->getName();
        if ($routeName && str_contains($routeName, 'audit-logs')) {
            return $response;
        }

        // Only log state-changing methods by default
        $method = strtoupper($request->getMethod());
        $shouldLog = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        if ($shouldLog) {
            $this->logRequest($request, $routeName);
        }

        return $response;
    }

    protected function logRequest(Request $request, ?string $routeName): void
    {
        try {
            $user = $request->user();

            $input = $this->sanitize($request->all());

            // Merge optional, controller-provided audit extras
            $extra = $request->attributes->get('audit_extra');
            if (is_array($extra)) {
                $input = array_merge($input, $this->sanitize($extra));
            }

            AuditLog::create([
                'user_id'    => $user?->id,
                'method'     => strtoupper($request->getMethod()),
                'path'       => '/'.ltrim($request->path(), '/'),
                'route_name' => $routeName,
                'action'     => $routeName ? $routeName : (strtoupper($request->getMethod()).' '.$request->path()),
                'description'=> $input,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1024),
            ]);
        } catch (\Throwable $e) {
            // Never break the app if logging fails.
        }
    }

    protected function sanitize(array $data): array
    {
        $blacklist = ['password', 'password_confirmation', 'current_password', '_token'];

        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $blacklist, true)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                $clean[$key] = 'uploaded_file:'.$value->getClientOriginalName();
            } elseif (is_object($value)) {
                $clean[$key] = '[object]';
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
