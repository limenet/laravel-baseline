<?php

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(object $user): void
    {
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(object $user): void
    {
    }
}
-----
<?php

class UserObserver
{
    public function created(object $user): void
    {
    }

    public function deleted(object $user): void
    {
    }
}
