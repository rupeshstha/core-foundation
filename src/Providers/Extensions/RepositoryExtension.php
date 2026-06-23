<?php

namespace CoreFoundation\Providers\Extensions;

use CoreFoundation\Repositories\BaseRepository;

/**
 * RepositoryExtension
 *
 * Fluent builder for extending a BaseRepository subclass from a module ServiceProvider.
 * Obtained via BaseExtensionServiceProvider::repository() — never instantiated directly.
 *
 * USAGE:
 *
 *   $this->repository(ProductRepository::class)
 *       ->scopeable(['onSale', 'lowStock']);
 *
 * @see BaseRepository::addScopeable()
 */
final class RepositoryExtension
{
    /**
     * @param  class-string<BaseRepository>  $repository
     */
    public function __construct(private readonly string $repository) {}

    /**
     * Register additional Eloquent local scope names this repository allows
     * via criteria['scopes'] — without touching the repository's source.
     *
     *   ->scopeable(['onSale', 'lowStock'])
     */
    public function scopeable(array $scopes): static
    {
        ($this->repository)::addScopeable($scopes);

        return $this;
    }
}
