<?php

namespace App\Http\Controllers;

use App\Modules\Merchandising\Models\MerchandisingFixtureType;
use App\Modules\Merchandising\Models\MerchandisingFloorPlan;
use App\Modules\Merchandising\Services\FloorPlanLayoutValidator;
use App\Modules\Merchandising\Services\MerchandisingFixtureTypeDefaults;
use App\Modules\Operations\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Tenancy\TenantOperationalScope;

class MerchandisingFloorPlanController extends Controller
{
    public function index(
        Request $request,
        MerchandisingFixtureTypeDefaults $defaults,
    ): View {
        $defaults->sync();
		$scope = $request->attributes->get('tenantOperationalScope');
		$branches = Branch::query()
			->where('status', 'active')
			->when(
				$scope['branch_id'] ?? null,
				fn ($query, $branchId) => $query->where('id', $branchId),
			)
			->orderBy('name')
			->get();
        $selectedBranch = $request->filled('branch_id')
            ? $branches->firstWhere('id', $request->integer('branch_id'))
            : $branches->first();

        if ($request->filled('branch_id') && ! $selectedBranch) {
            abort(404);
        }

        $floorPlans = $selectedBranch
            ? MerchandisingFloorPlan::query()
                ->where('branch_id', $selectedBranch->id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
            : collect();
        $floorPlan = $request->filled('floor_plan_id')
            ? $floorPlans->firstWhere('id', $request->integer('floor_plan_id'))
            : $floorPlans->first();

        if ($request->filled('floor_plan_id') && ! $floorPlan) {
            abort(404);
        }

        $floorPlan?->load([
            'items' => fn ($items) => $items->orderBy('sort_order'),
            'items.fixtureType',
        ]);

        return view('tenant.merchandising.floor-plan', [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'floorPlans' => $floorPlans,
            'floorPlan' => $floorPlan,
			'canManage' => app(TenantOperationalScope::class)->canManageTenant($scope),
            'fixtureTypes' => MerchandisingFixtureType::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('tenant.branches', 'id')
                    ->where('status', 'active'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'canvas_width' => ['required', 'integer', 'between:400,5000'],
            'canvas_height' => ['required', 'integer', 'between:300,5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $floorPlan = MerchandisingFloorPlan::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'canvas_width' => $data['canvas_width'],
            'canvas_height' => $data['canvas_height'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('merchandising.floor-plan', [
                'branch_id' => $data['branch_id'],
                'floor_plan_id' => $floorPlan->id,
            ])
            ->with('success', 'Floor Plan creado para la sucursal.');
    }

    public function update(
        Request $request,
        MerchandisingFloorPlan $floorPlan,
        FloorPlanLayoutValidator $layoutValidator,
    ): RedirectResponse {
        $payload = json_decode(
            $request->string('layout')->toString(),
            true,
        );

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'layout' => 'El layout enviado no es válido.',
            ]);
        }

        $data = validator(
            [
                'name' => $request->input('name'),
                'canvas_width' => $request->input('canvas_width'),
                'canvas_height' => $request->input('canvas_height'),
                'is_active' => $request->boolean('is_active'),
                'items' => $payload,
            ],
            [
                'name' => ['required', 'string', 'max:150'],
                'canvas_width' => ['required', 'integer', 'between:400,5000'],
                'canvas_height' => ['required', 'integer', 'between:300,5000'],
                'is_active' => ['required', 'boolean'],
                'items' => ['array', 'max:300'],
                'items.*.client_key' => [
                    'required',
                    'string',
                    'max:100',
                    'distinct',
                ],
                'items.*.parent_client_key' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'items.*.fixture_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('tenant.merchandising_fixture_types', 'id')
                        ->where('is_active', true),
                ],
                'items.*.label' => ['nullable', 'string', 'max:150'],
                'items.*.position_x' => ['required', 'numeric', 'between:0,92'],
                'items.*.position_y' => ['required', 'numeric', 'between:0,88'],
                'items.*.width' => ['required', 'numeric', 'between:4,40'],
                'items.*.height' => ['required', 'numeric', 'between:6,50'],
                'items.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            ],
        )->validate();

        $fixtureTypes = MerchandisingFixtureType::query()
            ->whereIn(
                'id',
                collect($data['items'])->pluck('fixture_type_id')->unique(),
            )
            ->get()
            ->keyBy('id');

        $layoutValidator->validate($data['items'], $fixtureTypes);

        DB::connection('tenant')->transaction(
            function () use ($floorPlan, $data): void {
                $floorPlan->update([
                    'name' => $data['name'],
                    'canvas_width' => $data['canvas_width'],
                    'canvas_height' => $data['canvas_height'],
                    'is_active' => $data['is_active'],
                ]);
                $floorPlan->items()->update([
                    'parent_item_id' => null,
                ]);
                $floorPlan->items()->delete();
                $createdItems = [];

                foreach ($data['items'] as $sortOrder => $item) {
                    $createdItems[$item['client_key']] = $floorPlan->items()->create([
                        'fixture_type_id' => $item['fixture_type_id'],
                        'parent_item_id' => null,
                        'label' => ($item['label'] ?? '') ?: null,
                        'position_x' => $item['position_x'],
                        'position_y' => $item['position_y'],
                        'width' => $item['width'],
                        'height' => $item['height'],
                        'rotation' => $item['rotation'],
                        'sort_order' => $sortOrder,
                    ]);
                }

                foreach ($data['items'] as $item) {
                    $parentKey = $item['parent_client_key'] ?? null;

                    if (! $parentKey) {
                        continue;
                    }

                    $createdItems[$item['client_key']]->update([
                        'parent_item_id' => $createdItems[$parentKey]->id,
                    ]);
                }
            },
        );

        return redirect()
            ->route('merchandising.floor-plan', [
                'branch_id' => $floorPlan->branch_id,
                'floor_plan_id' => $floorPlan->id,
            ])
            ->with('success', 'Floor Plan guardado.');
    }
}
