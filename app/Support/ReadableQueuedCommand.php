<?php

namespace App\Support;

use Illuminate\Foundation\Console\QueuedCommand;

class ReadableQueuedCommand extends QueuedCommand
{
    public function getData()
    {
        return $this->data;
    }
}
