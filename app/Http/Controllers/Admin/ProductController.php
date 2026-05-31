<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Craftsman;
use App\Models\ProductImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Vercel\Blob\BlobClient;

class ProductController extends Controller
{
    private function getDisk()
    {
        return (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1')
            ? 'vercel' : env('FILESYSTEM_DISK', 'public');
    }

    /**
     * Upload file to Vercel Blob storage using REST API
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $path Directory path (e.g., 'products', 'gallery')
     * @return string Public URL or local path
     */
    private function uploadToVercelBlob($file, $path = 'products')
    {
        // Check if we're on Vercel
        $isVercel = (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1');
        
        if (!$isVercel) {
            // Local development - use local storage
            return $file->store($path, 'public');
        }

        // Get Vercel Blob credentials
        $token = env('BLOB_READ_WRITE_TOKEN');
        $storeId = env('BLOB_STORE_ID');

        if (!$token || !$storeId) {
            Log::warning('Vercel Blob credentials missing. Falling back to local storage.');
            return $file->store($path, 'public');
        }

        try {
            // Generate unique filename
            $filename = Str::uuid() . '_' . $file->getClientOriginalName();
            $fullPath = $path . '/' . $filename;
            
            // Vercel Blob URL format
            $url = "https://{$storeId}.blob.vercel-storage.com/{$fullPath}?access=public";
            
            // Read file content
            $fileContent = file_get_contents($file->getRealPath());
            
            // Upload to Vercel Blob using HTTP PUT
            $response = Http::withToken($token)
                ->withHeader('Content-Type', $file->getMimeType())
                ->withHeader('Content-Disposition', 'inline; filename="' . $file->getClientOriginalName() . '"')
                ->put($url, $fileContent);
            
            if ($response->successful()) {
                $result = $response->json();
                
                // Return the public URL (Vercel Blob already includes URL in response)
                return $result['url'] ?? "https://{$storeId}.public.blob.vercel-storage.com/{$fullPath}";
            } else {
                Log::error('Vercel Blob upload failed: HTTP ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Vercel Blob upload exception: ' . $e->getMessage());
        }
        
        // Fallback to local storage on any error
        return $file->store($path, 'public');
    }

    /**
     * Delete file from Vercel Blob storage
     * 
     * @param string $url File URL (must be Vercel Blob URL)
     * @return bool
     */
    private function deleteFromVercelBlob($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        if (!str_contains($url, 'blob.vercel-storage.com')) {
            return false;
        }
        
        $token = env('BLOB_READ_WRITE_TOKEN');
        if (!$token) {
            Log::warning('BLOB_READ_WRITE_TOKEN not set. Cannot delete from Vercel Blob.');
            return false;
        }
        
        try {
            // Extract pathname from URL
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['path'])) {
                return false;
            }
            
            $pathname = ltrim($parsedUrl['path'], '/');
            
            // Vercel Blob delete API
            $apiUrl = 'https://api.vercel.com/v2/blobs';
            
            $response = Http::withToken($token)
                ->delete($apiUrl, [
                    'pathname' => $pathname,
                ]);
            
            if ($response->successful()) {
                Log::info('Deleted from Vercel Blob: ' . $pathname);
                return true;
            } else {
                Log::error('Vercel Blob delete failed: HTTP ' . $response->status() . ' - ' . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Vercel Blob delete exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete file (local or Vercel Blob)
     * 
     * @param string $disk Disk name
     * @param string $path File path or URL
     * @return bool
     */
    private function deleteStorageFile($disk, $path)
    {
        // Check if it's a Vercel Blob URL
        if (filter_var($path, FILTER_VALIDATE_URL) && str_contains($path, 'blob.vercel-storage.com')) {
            return $this->deleteFromVercelBlob($path);
        }
        
        // Delete from local disk
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }
        
        return false;
    }

    public function index()
    {
        $products = Product::with(['craftsman', 'images'])->orderBy('id', 'desc')->paginate(20);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $craftsmen = Craftsman::orderBy('name')->get();
        return view('admin.products.create', compact('craftsmen'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'craftsman_id' => 'nullable|exists:craftsmen,id',
            'name' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'wa' => 'nullable|string|max:20',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Upload main image with Vercel Blob or fallback
        if ($request->hasFile('image')) {
            $validated['image'] = $this->uploadToVercelBlob($request->file('image'), 'products');
        }

        $product = Product::create($validated);

        // Upload additional images
        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $index => $file) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $this->uploadToVercelBlob($file, 'products'),
                    'sort_order' => $index,
                ]);
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

        $product = Product::create($validated);

        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $index => $file) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $this->uploadToVercelBlob($file, 'products'),
                    'sort_order' => $index,
                ]);
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $craftsmen = Craftsman::orderBy('name')->get();
        $product->load('images');
        return view('admin.products.edit', compact('product', 'craftsmen'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'craftsman_id' => 'nullable|exists:craftsmen,id',
            'name' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'wa' => 'nullable|string|max:20',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $disk = $this->getDisk();
        
        // Delete old main image if replacing
        if ($request->hasFile('image')) {
            $oldImage = $product->getRawOriginal('image');
            if ($oldImage) {
                $this->deleteStorageFile($disk, $oldImage);
            }
            $validated['image'] = $this->uploadToVercelBlob($request->file('image'), 'products');
        }

        $product->update($validated);

        // Upload additional images
        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $index => $file) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $this->uploadToVercelBlob($file, 'products'),
                    'sort_order' => $product->images()->max('sort_order') + $index + 1,
                ]);
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $disk = $this->getDisk();
        
        // Delete main image
        $oldImage = $product->getRawOriginal('image');
        if ($oldImage) {
            $this->deleteStorageFile($disk, $oldImage);
        }

        // Delete all additional images
        foreach ($product->images as $img) {
            $imgRaw = $img->getRawOriginal('image');
            if ($imgRaw) {
                $this->deleteStorageFile($disk, $imgRaw);
            }
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            abort(403);
        }

        $disk = $this->getDisk();
        $imgRaw = $image->getRawOriginal('image');
        if ($imgRaw) {
            $this->deleteStorageFile($disk, $imgRaw);
        }

        $image->delete();

        return back()->with('success', 'Gambar berhasil dihapus.');
    }
}
