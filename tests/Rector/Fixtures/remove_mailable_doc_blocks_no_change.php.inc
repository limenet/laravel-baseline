<?php

use Illuminate\Mail\Mailable;

class OrderShipped extends Mailable
{
    /**
     * Accepts the order model and optionally a tracking number.
     */
    public function __construct()
    {
    }
}
