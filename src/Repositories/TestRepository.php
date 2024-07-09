<?php

namespace CoreFoundation\Repositories;

use App\Models\Role;
use CoreFoundation\Repositories\Interfaces\TestRepositoryInterface;

class TestRepository extends BaseRepository implements TestRepositoryInterface
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
