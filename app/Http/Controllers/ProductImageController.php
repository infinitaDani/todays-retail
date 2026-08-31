<?php

namespace App\Http\Controllers;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    public function show(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'product_variant_id' => ['nullable', 'integer'],
        ]);
        $variant = null;
        if ($data['product_variant_id'] ?? null) {
            $variant = $product->variants()->findOrFail($data['product_variant_id']);
        }
        $account = $request->attributes->get('tenantAccount');
        $directory = $variant
            ? "tenants/{$account->id}/products/{$product->id}/variants/{$variant->id}"
            : "tenants/{$account->id}/products/{$product->id}/images";
        $images = $variant ? $variant->ownImages() : $product->generalImages();
        $order = (int) $images->max('sort_order');
        foreach ($data['images'] as $file) {
            $path = $file->storeAs($directory, Str::uuid() . '.' . $file->extension(), 'local');
            ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'is_primary' => ! $images->exists(),
                'sort_order' => ++$order,
            ]);
        }
        return back()->with('success', 'Imágenes guardadas.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $scope = $image->product_variant_id ? $image->variant->ownImages() : $product->generalImages();
        $wasPrimary = $image->is_primary;
        Storage::disk('local')->delete($image->path);
        $image->delete();
        if ($wasPrimary && $next = $scope->first()) { $next->update(['is_primary' => true]); }
        return back()->with('success', 'Imagen eliminada.');
    }

    public function primary(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $query = ProductImage::where('product_id', $product->id)->where('product_variant_id', $image->product_variant_id);
        $query->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
        return back()->with('success', 'Imagen principal actualizada.');
    }
}
