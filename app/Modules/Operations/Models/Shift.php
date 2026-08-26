<?php
namespace App\Modules\Operations\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\HasMany;
class Shift extends TenantModel { protected $fillable=['name','start_time','end_time','status']; public function assignments(): HasMany { return $this->hasMany(Assignment::class); } public function checklists(): HasMany { return $this->hasMany(\App\Modules\Tasks\Models\Checklist::class); } }
