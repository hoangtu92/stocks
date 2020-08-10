<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Arav extends Model
{
    //
    protected $primaryKey = 'id';
    protected $fillable = [
        'id',
        'code',
        'name',
        'final',
        'price_range',
        'start',
        'max',
        'lowest',
        'date'];
}
