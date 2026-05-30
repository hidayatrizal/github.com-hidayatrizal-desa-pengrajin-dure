<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Craftsman;
use App\Models\ProductImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private function getDisk()
    {
        return (getenv('VERCEL') !== false || ($_SERVER['VERCEL'] ?? null) === '1')
            ? 'vercel' : env('FILESYSTEM_DISK', 'public');
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

        $disk = $this->getDisk();
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', $disk);
        }

        $product = Product::create($validated);

        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $index => $file) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $file->store('products', $disk),
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
        if ($request->hasFile('image')) {
            $oldImage = $product->getRawOriginal('image');
            if ($oldImage && Storage::disk($disk)->exists($oldImage)) {
                Storage::disk($disk)->delete($oldImage);
            }
            $validated['image'] = $request->file('image')->store('products', $disk);
        }

        $product->update($validated);

        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $index => $file) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $file->store('products', $disk),
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
        $oldImage = $product->getRawOriginal('image');
        if ($oldImage && Storage::disk($disk)->exists($oldImage)) {
            Storage::disk($disk)->delete($oldImage);
        }

        foreach ($product->images as $img) {
            $imgRaw = $img->getRawOriginal('image');
            if ($imgRaw && Storage::disk($disk)->exists($imgRaw)) {
                Storage::disk($disk)->delete($imgRaw);
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
        if ($imgRaw && Storage::disk($disk)->exists($imgRaw)) {
            Storage::disk($disk)->delete($imgRaw);
        }

        $image->delete();

        return back()->with('success', 'Gambar berhasil dihapus.');
    }
}
