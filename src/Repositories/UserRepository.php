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

namespace Rinvex\Fort\Repositories;

use Illuminate\Support\Str;
use Rinvex\Fort\Traits\HasRoles;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Foundation\Application;
use Rinvex\Fort\Contracts\UserRepositoryContract;
use Rinvex\Fort\Contracts\AuthenticatableContract;
use Rinvex\Repository\Repositories\EloquentRepository;

class UserRepository extends EloquentRepository implements UserRepositoryContract
{
    use HasRoles;

    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * create a new user repository instance.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     *
     * @return void
     */
    public function __construct(Application $app, Hasher $hasher)
    {
        $this->setContainer($app)
             ->setHasher($hasher)
             ->setRepositoryId('rinvex.fort.user')
             ->setModel($app['config']['rinvex.fort.models.user']);
    }

    /**
     * {@inheritdoc}
     */
    public function findByToken($identifier, $token)
    {
        return $this->where($this->getAuthIdentifierName(), $identifier)
                    ->where($this->getRememberTokenName(), $token)
                    ->findFirst();
    }

    /**
     * {@inheritdoc}
     */
    public function findByCredentials(array $credentials)
    {
        if (empty($credentials)) {
            return;
        }

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // Eloquent User "model" that will be utilized by the Guard instances.
        $model = $this;

        foreach ($credentials as $key => $value) {
            if (! Str::contains($key, 'password')) {
                $model = $model->where($key, $value);
            }
        }

        return $model->findFirst();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes = [])
    {
        $social = array_pull($attributes, 'social', false);

        // Fire the register start event
        $this->getContainer('events')->fire('rinvex.fort.register'.$social.'.start', [$attributes]);

        // Prepare registration data
        $attributes['password'] = bcrypt(! $social ? $attributes['password'] : str_random());
        $attributes['active']   = ! config('rinvex.fort.registration.moderated');

        // Create new user
        $user = parent::create($attributes);

        // Fire the register success event
        $this->getContainer('events')->fire('rinvex.fort.register'.$social.'.success', [$user]);

        // Send verification if required
        if (! $social && config('rinvex.fort.verification.required')) {
            return app('rinvex.fort.verifier')->broker()->sendVerificationLink(['email' => $attributes['email']]);
        }

        // Registration completed successfully
        return ! $social ? static::AUTH_REGISTERED : $user;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(AuthenticatableContract $user, $token)
    {
        $this->update($user, [$this->getRememberTokenName() => $token]);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(AuthenticatableContract $user, array $credentials)
    {
        $plain = $credentials['password'];

        return $this->getHasher()->check($plain, $user->getAuthPassword());
    }

    /**
     * Gets the hasher implementation.
     *
     * @return \Illuminate\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        return $this->hasher;
    }

    /**
     * Sets the hasher implementation.
     *
     * @param \Illuminate\Contracts\Hashing\Hasher $hasher
     *
     * @return $this
     */
    public function setHasher(Hasher $hasher)
    {
        $this->hasher = $hasher;

        return $this;
    }
}
