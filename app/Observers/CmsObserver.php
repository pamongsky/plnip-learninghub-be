<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

class CmsObserver
{
    /**
     * Handle the "saved" event (create or update)
     */
    public function saved($model): void
    {
        Cache::forget('landing_page_data');
    }

    /**
     * Handle the "deleted" event
     */
    public function deleted($model): void
    {
        Cache::forget('landing_page_data');
    }
}
