<?php

use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    /**
     * Accepts the invoice model and optional currency override.
     */
    public function __construct()
    {
    }
}
