<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\Assignment;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\ScheduleSetting;
use App\Modules\Operations\Models\SchedulePeriod;
use App\Modules\Operations\Models\ScheduleAdjustment;
use App\Modules\Operations\Models\SchedulePeriodChangeRequest;
use App\Modules\Operations\Models\Shift;
use App\Modules\Operations\Models\StaffProfile;
use App\Modules\Requests\Models\TenantRequest;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyPlannerController extends Controller
{
    public function createPeriod(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $data = $request->validate(['branch_id' => ['nullable', 'integer'], 'month_key' => ['required', 'date_format:Y-m']]);
        $branchId = $this->branchId($request, $scope, true);
        SchedulePeriod::firstOrCreate(
            ['branch_id' => $branchId, 'month_key' => $data['month_key']],
            ['status' => 'draft', 'created_by_core_user_id' => $request->user()->id],
        );
        return redirect()->route('operations.planner', ['branch_id' => $branchId, 'month' => $data['month_key'].'-01', 'view' => 'month']);
    }
    public function plan(Request $request, TenantOperationalScope $scopes): View
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $branchId = $this->branchId($request, $scope);
        $month = Carbon::parse($request->input('month', now()->startOfMonth()))->startOfMonth();
        $week = Carbon::parse($request->input('week', $month))->startOfWeek();
        $viewMode = $request->string('view', 'month')->toString();
        abort_unless(in_array($viewMode, ['week', 'fortnight', 'month'], true), 422);
        $period = $branchId ? SchedulePeriod::where('branch_id', $branchId)->where('month_key', $month->format('Y-m'))->first() : null;
        $profiles = StaffProfile::query()->where('status', 'active')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->with('branch')->orderBy('first_name')->get();
        $userIds = $profiles->pluck('core_user_id');
        $assignments = Assignment::query()->with('shift')->whereIn('core_user_id', $userIds)->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->keyBy(fn ($assignment) => $assignment->core_user_id.'-'.$assignment->date->toDateString());
        $absences = TenantRequest::query()->where('status', 'approved')->whereIn('type', ['vacation', 'permission'])->whereIn('core_user_id', $userIds)->whereDate('starts_at', '<=', $week->copy()->addDays(6))->whereDate('ends_at', '>=', $week)->get();
        $users = $request->attributes->get('tenantAccount')->users()->whereIn('users.id', $userIds)->get()->keyBy('id');
        $fortnight = $request->integer('fortnight', 1) === 2 ? 2 : 1;
        $activeStart = $viewMode === 'fortnight' ? ($fortnight === 1 ? $month->copy()->startOfMonth() : $month->copy()->day(16)) : ($viewMode === 'week' ? $week->copy() : $month->copy()->startOfMonth());
        $activeEnd = $viewMode === 'fortnight' ? ($fortnight === 1 ? $month->copy()->day(min(15, $month->daysInMonth)) : $month->copy()->endOfMonth()) : ($viewMode === 'week' ? $week->copy()->endOfWeek() : $month->copy()->endOfMonth());
        $weeks = match ($viewMode) {
            'month' => collect(range(0, $month->copy()->endOfMonth()->endOfWeek()->diffInWeeks($month->copy()->startOfMonth()->startOfWeek())))->map(fn ($offset) => $month->copy()->startOfMonth()->startOfWeek()->addWeeks($offset)),
            'fortnight' => collect(range(0, $activeEnd->copy()->endOfWeek()->diffInWeeks($activeStart->copy()->startOfWeek())))->map(fn ($offset) => $activeStart->copy()->startOfWeek()->addWeeks($offset)),
            default => collect([$week]),
        };
        return view('tenant.operations.planner', ['scope'=>$scope,'branches'=>$this->branches($scope),'branchId'=>$branchId,'month'=>$month,'week'=>$week,'weeks'=>$weeks,'viewMode'=>$viewMode,'fortnight'=>$fortnight,'activeStart'=>$activeStart,'activeEnd'=>$activeEnd,'schedulePeriod'=>$period,'profiles'=>$profiles,'users'=>$users,'assignments'=>$assignments,'absences'=>$absences,'shifts'=>Shift::where('status','active')->orderBy('name')->get(),'settings'=>ScheduleSetting::firstOrCreate([])]);
    }

    public function save(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $data = $request->validate(['branch_id'=>['nullable','integer'],'week'=>['required','date'],'view'=>['nullable','in:week,fortnight,month'],'fortnight'=>['nullable','in:1,2'],'cells'=>['required','array'],'cells.*'=>['nullable','integer']]);
        $branchId = $this->branchId($request, $scope, true);
        $week = Carbon::parse($data['week'])->startOfWeek();
        $month = $week->copy()->startOfMonth();
        $rangeStart = ($data['view'] ?? 'month') === 'week' ? $week->copy() : (($data['view'] ?? 'month') === 'fortnight' ? (($data['fortnight'] ?? '1') === '2' ? $month->copy()->day(16) : $month->copy()->startOfMonth()) : $month->copy()->startOfMonth());
        $rangeEnd = ($data['view'] ?? 'month') === 'week' ? $week->copy()->endOfWeek() : (($data['view'] ?? 'month') === 'fortnight' ? (($data['fortnight'] ?? '1') === '2' ? $month->copy()->endOfMonth() : $month->copy()->day(min(15, $month->daysInMonth))) : $month->copy()->endOfMonth());
        $period = SchedulePeriod::firstOrCreate(['branch_id' => $branchId, 'month_key' => $week->format('Y-m')], ['status' => 'draft', 'created_by_core_user_id' => $request->user()->id]);
        $historicalRequest = $this->approvedHistoricalRequest($period, $request->user()->id);
        if ($period->status === 'pending') throw ValidationException::withMessages(['schedule' => 'El horario está pendiente de aprobación y no puede modificarse.']);
        if ($period->status === 'approved' && ! $request->boolean('adjustment_mode')) throw ValidationException::withMessages(['schedule' => 'El horario aprobado está congelado. Usa Modo ajustes.']);
        if ($this->isPastPeriod($period) && ! $historicalRequest) throw ValidationException::withMessages(['schedule' => 'Este período histórico requiere autorización aprobada para usar Modo ajustes.']);
        $allowedUsers = StaffProfile::where('branch_id', $branchId)->where('status','active')->pluck('core_user_id');
        DB::connection('tenant')->transaction(function () use ($data, $week, $branchId, $allowedUsers, $period, $request, $rangeStart, $rangeEnd): void {
            foreach ($data['cells'] as $key => $shiftId) {
                [$userId, $date] = explode(':', $key, 2);
                $userId = (int) $userId;
                if (! $allowedUsers->contains($userId) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new AuthorizationException('Celda fuera de alcance.');
                if (Carbon::parse($date)->format('Y-m') !== $period->month_key) throw ValidationException::withMessages(['cells' => 'No puedes modificar fechas fuera del período mensual.']);
                if (Carbon::parse($date)->lt($rangeStart) || Carbon::parse($date)->gt($rangeEnd)) throw ValidationException::withMessages(['cells' => 'No puedes modificar fechas fuera de la vista seleccionada.']);
                $existing = Assignment::where('core_user_id',$userId)->whereDate('date',$date)->first();
                if ($period->status === 'approved' && (int) ($existing?->shift_id ?? 0) !== (int) $shiftId) {
                    $reason = $request->input('adjustment_reasons.'.$key);
                    if (! $reason) throw ValidationException::withMessages(['adjustment_reasons.'.$key => 'Cada ajuste requiere un motivo.']);
                    ScheduleAdjustment::create(['schedule_period_id'=>$period->id,'schedule_period_change_request_id'=>$historicalRequest?->id,'branch_id'=>$branchId,'core_user_id'=>$userId,'date'=>$date,'previous_shift_id'=>$existing?->shift_id,'new_shift_id'=>$shiftId ?: null,'reason'=>$reason,'comment'=>$request->input('adjustment_comments.'.$key),'tenant_request_id'=>$request->input('adjustment_requests.'.$key),'changed_by_core_user_id'=>$request->user()->id]);
                }
                if (! $shiftId) { $existing?->delete(); continue; }
                Shift::whereKey($shiftId)->where('status','active')->firstOrFail();
                $absence = TenantRequest::where('core_user_id',$userId)->where('status','approved')->whereIn('type',['vacation','permission'])->whereDate('starts_at','<=',$date)->whereDate('ends_at','>=',$date)->exists();
                if ($absence) throw ValidationException::withMessages(['cells' => 'No puedes asignar un turno sobre una ausencia aprobada.']);
                Assignment::updateOrCreate(['core_user_id'=>$userId,'date'=>$date], ['branch_id'=>$branchId,'shift_id'=>$shiftId]);
            }
        });
        return back()->with('success','Semana guardada correctamente.');
    }

    public function submit(Request $request, TenantOperationalScope $scopes): RedirectResponse
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope,true); $month=$request->validate(['month_key'=>['required','date_format:Y-m']])['month_key']; $period=SchedulePeriod::firstOrCreate(['branch_id'=>$branchId,'month_key'=>$month],['status'=>'draft','created_by_core_user_id'=>$request->user()->id]); if($period->status==='approved') abort(422,'El horario ya fue aprobado.'); $period->update(['status'=>'pending','submitted_by_core_user_id'=>$request->user()->id,'submitted_at'=>now()]); return back()->with('success','Horario enviado a aprobación.'); }
    public function review(Request $request, SchedulePeriod $schedulePeriod, TenantOperationalScope $scopes): RedirectResponse
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)||($scope['branch_id']&&$scope['branch_id']!==$schedulePeriod->branch_id)) throw new AuthorizationException(); $data=$request->validate(['status'=>['required','in:approved,rejected'],'review_comment'=>['required_if:status,rejected','nullable','string','max:2000']]); $schedulePeriod->update(['status'=>$data['status'],'reviewed_by_core_user_id'=>$request->user()->id,'reviewed_at'=>now(),'review_comment'=>$data['review_comment']??null]); return back()->with('success','Revisión registrada.'); }
    public function adjustments(Request $request, TenantOperationalScope $scopes): View
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope); $items=ScheduleAdjustment::query()->when($branchId,fn($q)=>$q->where('branch_id',$branchId))->when($request->filled('core_user_id'),fn($q)=>$q->where('core_user_id',$request->integer('core_user_id')))->latest()->paginate(30); return view('tenant.operations.schedule-adjustments',compact('items','scope')); }

    public function changeRequests(Request $request, TenantOperationalScope $scopes): View
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $query=SchedulePeriodChangeRequest::query()->latest(); if(! $scopes->canManageTenant($scope)) $query->where('requested_by_core_user_id',$request->user()->id); return view('tenant.operations.schedule-change-requests',['items'=>$query->paginate(30),'scope'=>$scope]); }
    public function requestHistoricalChange(Request $request, SchedulePeriod $schedulePeriod, TenantOperationalScope $scopes): RedirectResponse
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if($scope['branch_id']&&$scope['branch_id']!==$schedulePeriod->branch_id) throw new AuthorizationException(); $data=$request->validate(['reason'=>['required','string','max:2000']]); SchedulePeriodChangeRequest::firstOrCreate(['schedule_period_id'=>$schedulePeriod->id,'requested_by_core_user_id'=>$request->user()->id,'status'=>'pending'],['reason'=>$data['reason'],'requested_at'=>now()]); return back()->with('success','Solicitud de autorización enviada.'); }
    public function resolveHistoricalChange(Request $request, SchedulePeriodChangeRequest $changeRequest, TenantOperationalScope $scopes): RedirectResponse
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)||$changeRequest->requested_by_core_user_id===$request->user()->id) throw new AuthorizationException(); $data=$request->validate(['status'=>['required','in:approved,rejected'],'review_comment'=>['required_if:status,rejected','nullable','string','max:2000']]); $changeRequest->update(['status'=>$data['status'],'reviewed_by_core_user_id'=>$request->user()->id,'reviewed_at'=>now(),'review_comment'=>$data['review_comment']??null]); return back()->with('success','Solicitud resuelta.'); }

    public function copy(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope,true); $week=Carbon::parse($request->validate(['week'=>['required','date']])['week'])->startOfWeek(); $ids=StaffProfile::where('branch_id',$branchId)->pluck('core_user_id');
        $period = SchedulePeriod::where('branch_id',$branchId)->where('month_key',$week->format('Y-m'))->firstOrFail();
        DB::connection('tenant')->transaction(function () use ($week,$branchId,$ids,$period): void { $previous=Assignment::whereIn('core_user_id',$ids)->whereBetween('date',[$week->copy()->subWeek()->toDateString(),$week->copy()->subDay()->toDateString()])->get(); foreach($previous as $assignment){$date=Carbon::parse($assignment->date)->addWeek(); if($date->format('Y-m')!==$period->month_key) continue; Assignment::updateOrCreate(['core_user_id'=>$assignment->core_user_id,'date'=>$date->toDateString()],['branch_id'=>$branchId,'shift_id'=>$assignment->shift_id]);} });
        return back()->with('success','Semana anterior duplicada.');
    }

    public function report(Request $request, TenantOperationalScope $scopes): View
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope); $week=Carbon::parse($request->input('week',now()))->startOfWeek(); $settings=ScheduleSetting::firstOrCreate([]); $profiles=StaffProfile::where('status','active')->when($branchId,fn($q)=>$q->where('branch_id',$branchId))->get(); $assignments=Assignment::with('shift')->whereIn('core_user_id',$profiles->pluck('core_user_id'))->whereBetween('date',[$week,$week->copy()->addDays(6)])->get()->groupBy('core_user_id'); return view('tenant.operations.schedule-report',compact('scope','week','settings','profiles','assignments','branchId')); }

    public function settings(Request $request, TenantOperationalScope $scopes): View { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)) throw new AuthorizationException(); return view('tenant.operations.schedule-settings',['settings'=>ScheduleSetting::firstOrCreate([])]); }
    public function updateSettings(Request $request, TenantOperationalScope $scopes): RedirectResponse { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)) throw new AuthorizationException(); ScheduleSetting::firstOrCreate([])->update($request->validate(['expected_hours_per_week'=>['required','numeric','min:0'],'standard_hours_per_day'=>['required','numeric','min:0'],'required_days_off_per_week'=>['required','integer','min:0','max:7'],'warn_on_excess_hours'=>['nullable','boolean']])); return back()->with('success','Configuración guardada.'); }
    private function branches(array $scope){return Branch::where('status','active')->when($scope['branch_id'],fn($q)=>$q->whereKey($scope['branch_id']))->orderBy('name')->get();}
    private function branchId(Request $request,array $scope,bool $required=false): ?int {if($scope['branch_id']) return $scope['branch_id'];$id=$request->integer('branch_id');if($required&&!$id) throw ValidationException::withMessages(['branch_id'=>'Selecciona una sucursal.']);if($id&&!Branch::whereKey($id)->where('status','active')->exists())throw new AuthorizationException('Sucursal no disponible.');return $id;}
    private function isPastPeriod(SchedulePeriod $period): bool { return Carbon::createFromFormat('Y-m',$period->month_key)->endOfMonth()->lt(now()->startOfDay()); }
    private function approvedHistoricalRequest(SchedulePeriod $period, int $userId): ?SchedulePeriodChangeRequest { return SchedulePeriodChangeRequest::where('schedule_period_id',$period->id)->where('requested_by_core_user_id',$userId)->where('status','approved')->latest('reviewed_at')->first(); }
}
