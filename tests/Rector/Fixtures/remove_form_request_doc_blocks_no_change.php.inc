<?php

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Only authenticated users with the post:create permission may proceed.
     */
    public function authorize(): bool
    {
        return false;
    }
}
