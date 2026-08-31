<?php

namespace App\Http\Controllers;

use App\Modules\Products\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductTypeController extends Controller
{
    public function index(): View
    {
        return view('tenant.products.types.index', [
            'types' => ProductType::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('tenant.products.types.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $type = new ProductType();
        $this->persist($request, $type);

        return redirect()->route('products.types.index')->with('success', 'Tipo de producto creado.');
    }

    public function edit(ProductType $productType): View
    {
        return view('tenant.products.types.form', compact('productType'));
    }

    public function update(Request $request, ProductType $productType): RedirectResponse
    {
        $this->persist($request, $productType);

        return redirect()->route('products.types.index')->with('success', 'Tipo de producto actualizado.');
    }

    public function toggle(ProductType $productType): RedirectResponse
    {
        $productType->update(['is_active' => ! $productType->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    private function persist(Request $request, ProductType $productType): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $normalized = mb_strtolower(Str::squish($data['name']));

        validator(['normalized_name' => $normalized], [
            'normalized_name' => [Rule::unique('tenant.product_types', 'normalized_name')->ignore($productType->id)],
        ])->validate();

        $productType->fill([
            'name' => Str::squish($data['name']),
            'normalized_name' => $normalized,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ])->save();
    }
}
