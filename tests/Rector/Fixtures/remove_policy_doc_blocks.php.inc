<?php

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(object $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(object $user, object $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(object $user, object $post): bool
    {
        return false;
    }
}
-----
<?php

class PostPolicy
{
    public function viewAny(object $user): bool
    {
        return false;
    }

    public function update(object $user, object $post): bool
    {
        return false;
    }

    public function forceDelete(object $user, object $post): bool
    {
        return false;
    }
}
