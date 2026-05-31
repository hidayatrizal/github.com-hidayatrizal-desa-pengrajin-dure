<?php

namespace App\Http\Controllers\Admin;

use App\Models\Craftsman;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CraftsmanController extends Controller
{
    private function getDisk()
    {
        return (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1')
            ? 'vercel' : env('FILESYSTEM_DISK', 'public');
    }

    private function uploadToVercelBlob($file, $path = 'craftsmen')
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

    private function deleteFromVercelBlob($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        if (!str_contains($url, 'blob.vercel-storage.com')) return false;
        
        $token = env('BLOB_READ_WRITE_TOKEN');
        if (!$token) {
            Log::warning('BLOB_READ_WRITE_TOKEN not set.');
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
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Vercel Blob delete exception: ' . $e->getMessage());
            return false;
        }
    }

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
        $craftsmen = Craftsman::withCount('products')->orderBy('id', 'desc')->get();
        return view('admin.craftsmen.index', compact('craftsmen'));
    }

    public function create()
    {
        return view('admin.craftsmen.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'capacity' => 'nullable|integer|min:0',
            'price' => 'nullable|string|max:255',
            'wa' => 'nullable|string|max:20',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->uploadToVercelBlob($request->file('image'), 'craftsmen');
        }

        Craftsman::create($validated);

        return redirect()->route('admin.craftsmen.index')
            ->with('success', 'Toko/UMKM berhasil ditambahkan.');
    }

    public function edit(Craftsman $craftsman)
    {
        return view('admin.craftsmen.edit', compact('craftsman'));
    }

    public function update(Request $request, Craftsman $craftsman)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'capacity' => 'nullable|integer|min:0',
            'price' => 'nullable|string|max:255',
            'wa' => 'nullable|string|max:20',
        ]);

        $disk = $this->getDisk();
        if ($request->hasFile('image')) {
            $oldImage = $craftsman->getRawOriginal('image');
            if ($oldImage) {
                $this->deleteStorageFile($disk, $oldImage);
            }
            $validated['image'] = $this->uploadToVercelBlob($request->file('image'), 'craftsmen');
        }

        $craftsman->update($validated);

        return redirect()->route('admin.craftsmen.index')
            ->with('success', 'Toko/UMKM berhasil diperbarui.');
    }

    public function destroy(Craftsman $craftsman)
    {
        $disk = $this->getDisk();
        $oldImage = $craftsman->getRawOriginal('image');
        if ($oldImage) {
            $this->deleteStorageFile($disk, $oldImage);
        }

        $craftsman->delete();

        return redirect()->route('admin.craftsmen.index')
            ->with('success', 'Toko/UMKM berhasil dihapus.');
    }
}
