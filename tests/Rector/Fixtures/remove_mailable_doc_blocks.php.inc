<?php

use Illuminate\Mail\Mailable;

class OrderShipped extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct()
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): object
    {
        return new \stdClass();
    }

    /**
     * Get the message content definition.
     */
    public function content(): object
    {
        return new \stdClass();
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
-----
<?php

use Illuminate\Mail\Mailable;

class OrderShipped extends Mailable
{
    public function __construct()
    {
    }

    public function envelope(): object
    {
        return new \stdClass();
    }

    public function content(): object
    {
        return new \stdClass();
    }

    public function attachments(): array
    {
        return [];
    }
}
