<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    private function getDisk()
    {
        return (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1')
            ? 'vercel' : env('FILESYSTEM_DISK', 'public');
    }

    /**
     * Upload file to Vercel Blob storage using REST API
     */
    private function uploadToVercelBlob($file, $path = 'gallery')
    {
        $isVercel = (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1');
        
        if (!$isVercel) {
            return $file->store($path, 'public');
        }

        $token = env('BLOB_READ_WRITE_TOKEN');
        $storeId = env('BLOB_STORE_ID');

        if (!$token || !$storeId) {
            Log::warning('Vercel Blob credentials missing. Falling back to local storage.');
            return $file->store($path, 'public');
        }

        try {
            $filename = Str::uuid() . '_' . $file->getClientOriginalName();
            $fullPath = $path . '/' . $filename;
            $url = "https://{$storeId}.blob.vercel-storage.com/{$fullPath}?access=public";
            
            $fileContent = file_get_contents($file->getRealPath());
            
            $response = Http::withToken($token)
                ->withHeader('Content-Type', $file->getMimeType())
                ->withHeader('Content-Disposition', 'inline; filename="' . $file->getClientOriginalName() . '"')
                ->put($url, $fileContent);
            
            if ($response->successful()) {
                $result = $response->json();
                return $result['url'] ?? "https://{$storeId}.public.blob.vercel-storage.com/{$fullPath}";
            } else {
                Log::error('Vercel Blob upload failed: HTTP ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Vercel Blob upload exception: ' . $e->getMessage());
        }
        
        return $file->store($path, 'public');
    }

    /**
     * Delete file from Vercel Blob storage
     */
    private function deleteFromVercelBlob($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        if (!str_contains($url, 'blob.vercel-storage.com')) return false;
        
        $token = env('BLOB_READ_WRITE_TOKEN');
        if (!$token) {
            Log::warning('BLOB_READ_WRITE_TOKEN not set. Cannot delete from Vercel Blob.');
            return false;
        }
        
        try {
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['path'])) return false;
            
            $pathname = ltrim($parsedUrl['path'], '/');
            $apiUrl = 'https://api.vercel.com/v2/blobs';
            
            $response = Http::withToken($token)
                ->delete($apiUrl, ['pathname' => $pathname]);
            
            if ($response->successful()) {
                Log::info('Deleted from Vercel Blob: ' . $pathname);
                return true;
            } else {
                Log::error('Vercel Blob delete failed: HTTP ' . $response->status());
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Vercel Blob delete exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete file (local or Vercel Blob)
     */
    private function deleteStorageFile($disk, $path)
    {
        if (filter_var($path, FILTER_VALIDATE_URL) && str_contains($path, 'blob.vercel-storage.com')) {
            return $this->deleteFromVercelBlob($path);
        }
        
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }
        
        return false;
    }

    public function index()
    {
        $galleries = Gallery::orderBy('id', 'desc')->get();
        return view('admin.gallery.index', compact('galleries'));
    }

    public function create()
    {
        return view('admin.gallery.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'url' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('url')) {
            $validated['url'] = $this->uploadToVercelBlob($request->file('url'), 'gallery');
        }

        Gallery::create($validated);

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil ditambahkan.');
    }

        Gallery::create($validated);

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil ditambahkan.');
    }

    public function edit(Gallery $gallery)
    {
        return view('admin.gallery.edit', compact('gallery'));
    }

    public function update(Request $request, Gallery $gallery)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'url' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $disk = $this->getDisk();
        if ($request->hasFile('url')) {
            $oldUrl = $gallery->getRawOriginal('url');
            if ($oldUrl) {
                $this->deleteStorageFile($disk, $oldUrl);
            }
            $validated['url'] = $this->uploadToVercelBlob($request->file('url'), 'gallery');
        }

        $gallery->update($validated);

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil diperbarui.');
    }

    public function destroy(Gallery $gallery)
    {
        $disk = $this->getDisk();
        $oldUrl = $gallery->getRawOriginal('url');
        if ($oldUrl) {
            $this->deleteStorageFile($disk, $oldUrl);
        }

        $gallery->delete();

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil dihapus.');
    }
}
