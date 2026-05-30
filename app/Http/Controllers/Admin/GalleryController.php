<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
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

        // Use vercel disk when on Vercel, otherwise use configured disk
        $disk = (env('IS_NOW') || env('VERCEL')) ? 'vercel' : env('FILESYSTEM_DISK', 'public');
        if ($request->hasFile('url')) {
            $validated['url'] = $request->file('url')->store('gallery', $disk);
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

        // Use vercel disk when on Vercel, otherwise use configured disk
        $disk = (env('IS_NOW') || env('VERCEL')) ? 'vercel' : env('FILESYSTEM_DISK', 'public');
        if ($request->hasFile('url')) {
            $oldUrl = $gallery->getRawOriginal('url');
            if ($oldUrl && Storage::disk($disk)->exists($oldUrl)) {
                Storage::disk($disk)->delete($oldUrl);
            }
            $validated['url'] = $request->file('url')->store('gallery', $disk);
        }

        $gallery->update($validated);

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil diperbarui.');
    }

    public function destroy(Gallery $gallery)
    {
        $disk = env('FILESYSTEM_DISK', 'public');
        $oldUrl = $gallery->getRawOriginal('url');
        if ($oldUrl && Storage::disk($disk)->exists($oldUrl)) {
            Storage::disk($disk)->delete($oldUrl);
        }

        $gallery->delete();

        return redirect()->route('admin.gallery.index')
            ->with('success', 'Foto galeri berhasil dihapus.');
    }
}