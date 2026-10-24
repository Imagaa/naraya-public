<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One store on one weekday of a sales person's weekly schedule (App\Support\Sales\StoreSchedules). */
#[Fillable(['sales_user_id', 'buyer_id', 'weekday'])]
class StoreSchedule extends Model
{
    protected $table = 'sales_store_schedules';
}
