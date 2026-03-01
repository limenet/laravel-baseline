<?php

use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): object
    {
        return new \stdClass();
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
-----
<?php

use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    public function __construct()
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): object
    {
        return new \stdClass();
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
