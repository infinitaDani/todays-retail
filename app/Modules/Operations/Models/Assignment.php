<?php
namespace App\Modules\Operations\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Assignment extends TenantModel { protected $fillable=['core_user_id','branch_id','shift_id','date']; protected function casts(): array { return ['date'=>'date']; } public function branch(): BelongsTo { return $this->belongsTo(Branch::class); } public function shift(): BelongsTo { return $this->belongsTo(Shift::class); } }
