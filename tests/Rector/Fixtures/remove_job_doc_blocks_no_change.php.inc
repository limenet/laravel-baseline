<?php

use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPodcast implements ShouldQueue
{
    /**
     * Accepts a podcast model and optional priority flag.
     */
    public function __construct()
    {
    }

    public function handle(): void
    {
    }
}
