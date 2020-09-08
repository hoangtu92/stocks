<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedCrawl extends Model
{
    //
    protected $table = 'failed_crawls';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;
    public $fillable = ["action", "restart", "resolved", "id", "failed_at"];
}
