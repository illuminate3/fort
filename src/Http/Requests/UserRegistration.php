<?php

/*
 * NOTICE OF LICENSE
 *
 * Part of the Rinvex Fort Package.
 *
 * This source file is subject to The MIT License (MIT)
 * that is bundled with this package in the LICENSE file.
 *
 * Package: Rinvex Fort Package
 * License: The MIT License (MIT)
 * Link:    https://rinvex.com
 */

namespace Rinvex\Fort\Http\Requests;

use Rinvex\Support\Http\Requests\FormRequest;

class UserRegistration extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function forbiddenResponse()
    {
        return intend([
            'intended'   => url('/'),
            'withErrors' => ['rinvex.fort.registration.disabled' => trans('rinvex.fort::frontend/messages.register.disabled')],
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return config('rinvex.fort.registration.enabled');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return $this->isMethod('post') ? [
            'email'    => 'required|email|max:255|unique:'.config('rinvex.fort.tables.users').',email',
            'username' => 'required|max:255|unique:'.config('rinvex.fort.tables.users').',username',
            'password' => 'required|min:6|confirmed',
        ] : [];
    }
}
