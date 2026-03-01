<?php

class PostPolicy
{
    /**
     * Only admins can view any posts.
     */
    public function viewAny(object $user): bool
    {
        return false;
    }
}
