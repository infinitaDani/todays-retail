<?php
namespace App\Modules\Tasks\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\HasMany;
class Task extends TenantModel { protected $fillable=['name','description','status']; public function checklistItems(): HasMany { return $this->hasMany(ChecklistItem::class); } }
