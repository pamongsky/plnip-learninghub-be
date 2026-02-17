<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only compress if client accepts gzip
        if (!str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            return $response;
        }

        // Only compress text-based content
        $contentType = $response->headers->get('Content-Type', '');
        if (!$this->shouldCompress($contentType)) {
            return $response;
        }

        // Don't compress if already compressed
        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        // Get response content
        $content = $response->getContent();

        // Only compress if content is large enough (>1KB)
        if (strlen($content) < 1024) {
            return $response;
        }

        // Compress with gzip
        $compressed = gzencode($content, 6); // Compression level 6 (balanced)

        if ($compressed !== false) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', strlen($compressed));
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }

    /**
     * Determine if content type should be compressed
     */
    protected function shouldCompress(string $contentType): bool
    {
        $compressibleTypes = [
            'text/',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/x-javascript',
        ];

        foreach ($compressibleTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }
}
