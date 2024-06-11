<?php

namespace CoreFoundation\Repositories;

use App\Models\Role;
use App\Models\User;
use CoreFoundation\Transformers\TestResource;

class TestRepository extends BaseRepository
{
    // public function __construct(
    //     protected User $user
    // ) {
    //     $this->model = $user;
    //     parent::__construct();
    // }

    protected function setModel(): string
    {
        return Role::class; // check
    }

    // public function register(): void
    // {
    //     $this->model = $this->app->make(User::class);
    // }
}
