<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone running the Naraya Sales Android app; pushes go to it through FCM (App\Support\Mobile\Fcm). */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token'];
}
