<?php

namespace App\Http\Controllers;

use App\Modules\Merchandising\Models\MerchandisingFixtureType;
use App\Modules\Merchandising\Services\MerchandisingFixtureTypeDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MerchandisingFixtureTypeController extends Controller
{
    public function index(MerchandisingFixtureTypeDefaults $defaults): View
    {
        $defaults->sync();

        return view('tenant.merchandising.fixture-types.index', [
            'fixtureTypes' => MerchandisingFixtureType::query()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('tenant.merchandising.fixture-types.form');
    }

    public function store(
        Request $request,
        MerchandisingFixtureTypeDefaults $defaults,
    ): RedirectResponse {
        $data = $this->validated($request);
        $normalizedName = $defaults->normalize($data['name']);

        $this->validateUniqueName(
            $normalizedName,
            $data['category'],
        );

        MerchandisingFixtureType::create([
            'code' => 'custom-' . Str::uuid(),
            'name' => Str::squish($data['name']),
            'normalized_name' => $normalizedName,
            'category' => $data['category'],
            'icon_path' => null,
            'is_default' => false,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('merchandising.fixture-types.index')
            ->with('success', 'Elemento de merchandising creado.');
    }

    public function edit(MerchandisingFixtureType $fixtureType): View
    {
        return view('tenant.merchandising.fixture-types.form', [
            'fixtureType' => $fixtureType,
        ]);
    }

    public function update(
        Request $request,
        MerchandisingFixtureType $fixtureType,
        MerchandisingFixtureTypeDefaults $defaults,
    ): RedirectResponse {
        $data = $this->validated($request);
        $normalizedName = $defaults->normalize($data['name']);
        $category = $fixtureType->is_default
            ? $fixtureType->category
            : $data['category'];

        $this->validateUniqueName(
            $normalizedName,
            $category,
            $fixtureType,
        );

        $fixtureType->update([
            'name' => Str::squish($data['name']),
            'normalized_name' => $normalizedName,
            'category' => $category,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('merchandising.fixture-types.index')
            ->with('success', 'Elemento de merchandising actualizado.');
    }

    public function toggle(MerchandisingFixtureType $fixtureType): RedirectResponse
    {
        $fixtureType->update([
            'is_active' => ! $fixtureType->is_active,
        ]);

        return back()->with('success', 'Estado del elemento actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => [
                'required',
                Rule::in([
                    MerchandisingFixtureType::CATEGORY_STRUCTURE,
                    MerchandisingFixtureType::CATEGORY_ACCESSORY,
                ]),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateUniqueName(
        string $normalizedName,
        string $category,
        ?MerchandisingFixtureType $fixtureType = null,
    ): void {
        validator(
            [
                'normalized_name' => $normalizedName,
                'category' => $category,
            ],
            [
                'normalized_name' => [
                    Rule::unique(
                        'tenant.merchandising_fixture_types',
                        'normalized_name',
                    )
                        ->where('category', $category)
                        ->ignore($fixtureType?->id),
                ],
            ],
            [
                'normalized_name.unique' => 'Ya existe un elemento con este nombre en la categoría seleccionada.',
            ],
        )->validate();
    }
}
