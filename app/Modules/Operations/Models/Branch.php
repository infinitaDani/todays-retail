<?php
namespace App\Modules\Operations\Models;
use App\Modules\TenantModel;
class Branch extends TenantModel { protected $fillable=['name','code','status']; }
