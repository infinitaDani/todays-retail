<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\Assignment;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\ScheduleSetting;
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
    public function plan(Request $request, TenantOperationalScope $scopes): View
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $branchId = $this->branchId($request, $scope);
        $week = Carbon::parse($request->input('week', now()))->startOfWeek();
        $profiles = StaffProfile::query()->where('status', 'active')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->with('branch')->orderBy('first_name')->get();
        $userIds = $profiles->pluck('core_user_id');
        $assignments = Assignment::query()->with('shift')->whereIn('core_user_id', $userIds)->whereBetween('date', [$week->toDateString(), $week->copy()->addDays(6)->toDateString()])->get()->keyBy(fn ($assignment) => $assignment->core_user_id.'-'.$assignment->date->toDateString());
        $absences = TenantRequest::query()->where('status', 'approved')->whereIn('type', ['vacation', 'permission'])->whereIn('core_user_id', $userIds)->whereDate('starts_at', '<=', $week->copy()->addDays(6))->whereDate('ends_at', '>=', $week)->get();
        $users = $request->attributes->get('tenantAccount')->users()->whereIn('users.id', $userIds)->get()->keyBy('id');
        return view('tenant.operations.planner', ['scope'=>$scope,'branches'=>$this->branches($scope),'branchId'=>$branchId,'week'=>$week,'profiles'=>$profiles,'users'=>$users,'assignments'=>$assignments,'absences'=>$absences,'shifts'=>Shift::where('status','active')->orderBy('name')->get(),'settings'=>ScheduleSetting::firstOrCreate([])]);
    }

    public function save(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $data = $request->validate(['branch_id'=>['nullable','integer'],'week'=>['required','date'],'cells'=>['required','array'],'cells.*'=>['nullable','integer']]);
        $branchId = $this->branchId($request, $scope, true);
        $week = Carbon::parse($data['week'])->startOfWeek();
        $allowedUsers = StaffProfile::where('branch_id', $branchId)->where('status','active')->pluck('core_user_id');
        DB::connection('tenant')->transaction(function () use ($data, $week, $branchId, $allowedUsers): void {
            foreach ($data['cells'] as $key => $shiftId) {
                [$userId, $offset] = array_map('intval', explode(':', $key));
                if (! $allowedUsers->contains($userId) || $offset < 0 || $offset > 6) throw new AuthorizationException('Celda fuera de alcance.');
                $date = $week->copy()->addDays($offset)->toDateString();
                if (! $shiftId) { Assignment::where('core_user_id',$userId)->whereDate('date',$date)->delete(); continue; }
                Shift::whereKey($shiftId)->where('status','active')->firstOrFail();
                $absence = TenantRequest::where('core_user_id',$userId)->where('status','approved')->whereIn('type',['vacation','permission'])->whereDate('starts_at','<=',$date)->whereDate('ends_at','>=',$date)->exists();
                if ($absence) throw ValidationException::withMessages(['cells' => 'No puedes asignar un turno sobre una ausencia aprobada.']);
                Assignment::updateOrCreate(['core_user_id'=>$userId,'date'=>$date], ['branch_id'=>$branchId,'shift_id'=>$shiftId]);
            }
        });
        return back()->with('success','Semana guardada correctamente.');
    }

    public function copy(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope,true); $week=Carbon::parse($request->validate(['week'=>['required','date']])['week'])->startOfWeek(); $ids=StaffProfile::where('branch_id',$branchId)->pluck('core_user_id');
        DB::connection('tenant')->transaction(function () use ($week,$branchId,$ids): void { $previous=Assignment::whereIn('core_user_id',$ids)->whereBetween('date',[$week->copy()->subWeek()->toDateString(),$week->copy()->subDay()->toDateString()])->get(); foreach($previous as $assignment){$date=Carbon::parse($assignment->date)->addWeek()->toDateString();Assignment::updateOrCreate(['core_user_id'=>$assignment->core_user_id,'date'=>$date],['branch_id'=>$branchId,'shift_id'=>$assignment->shift_id]);} });
        return back()->with('success','Semana anterior duplicada.');
    }

    public function report(Request $request, TenantOperationalScope $scopes): View
    { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); $branchId=$this->branchId($request,$scope); $week=Carbon::parse($request->input('week',now()))->startOfWeek(); $settings=ScheduleSetting::firstOrCreate([]); $profiles=StaffProfile::where('status','active')->when($branchId,fn($q)=>$q->where('branch_id',$branchId))->get(); $assignments=Assignment::with('shift')->whereIn('core_user_id',$profiles->pluck('core_user_id'))->whereBetween('date',[$week,$week->copy()->addDays(6)])->get()->groupBy('core_user_id'); return view('tenant.operations.schedule-report',compact('scope','week','settings','profiles','assignments','branchId')); }

    public function settings(Request $request, TenantOperationalScope $scopes): View { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)) throw new AuthorizationException(); return view('tenant.operations.schedule-settings',['settings'=>ScheduleSetting::firstOrCreate([])]); }
    public function updateSettings(Request $request, TenantOperationalScope $scopes): RedirectResponse { $scope=$scopes->for($request->user(),$request->attributes->get('tenantAccount')); if(! $scopes->canManageTenant($scope)) throw new AuthorizationException(); ScheduleSetting::firstOrCreate([])->update($request->validate(['expected_hours_per_week'=>['required','numeric','min:0'],'standard_hours_per_day'=>['required','numeric','min:0'],'required_days_off_per_week'=>['required','integer','min:0','max:7'],'warn_on_excess_hours'=>['nullable','boolean']])); return back()->with('success','Configuración guardada.'); }
    private function branches(array $scope){return Branch::where('status','active')->when($scope['branch_id'],fn($q)=>$q->whereKey($scope['branch_id']))->orderBy('name')->get();}
    private function branchId(Request $request,array $scope,bool $required=false): ?int {if($scope['branch_id']) return $scope['branch_id'];$id=$request->integer('branch_id');if($required&&!$id) throw ValidationException::withMessages(['branch_id'=>'Selecciona una sucursal.']);if($id&&!Branch::whereKey($id)->where('status','active')->exists())throw new AuthorizationException('Sucursal no disponible.');return $id;}
}
