<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mime;
use Illuminate\Support\Str;

class ServeController extends Controller
{
    public function serve($path)
    {
        $fullPath = '/tmp/storage/app/public/' . $path;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            abort(404);
        }

        $mime = mime_content_type($fullPath);
        $size = filesize($fullPath);
        $content = file_get_contents($fullPath);

        return response($content, 200, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Length' => $size,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
