<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * QueryType
 *
 * Distinguishes the nature of a cached query so the cache system can apply
 * the correct tag tier and flush only what's necessary.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ LISTING                                                                     │
 * │                                                                             │
 * │ Result contains multiple records (fetchAll, paginated queries, search).     │
 * │ Tagged with the listing tag.                                                │
 * │                                                                             │
 * │ Flushed on: create, update (any record in this model+scope), delete         │
 * │                                                                             │
 * │ Tag: {scope}:{table}:listing                                                │
 * │                                                                             │
 * │ WHY: A newly created or updated product must appear in the listing.         │
 * │ We can't know which listing query includes a specific record, so we         │
 * │ flush all listing caches for this tenant when any record changes.           │
 * │                                                                             │
 * │ Trade-off: One update = all listings for this tenant/model bust.            │
 * │ Acceptable at any tenant scale. Far better than busting ALL tenants.        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ RECORD                                                                      │
 * │                                                                             │
 * │ Result is for a single record (fetchById, findOrFail).                      │
 * │ Tagged with the record-specific tag.                                        │
 * │                                                                             │
 * │ Flushed on: update of THIS record, delete of THIS record                   │
 * │ NOT flushed when other records in the same model change.                   │
 * │                                                                             │
 * │ Tag: {scope}:{table}:record:{id}                                            │
 * │                                                                             │
 * │ WHY: Product 123 cache must be invalidated when product 123 is updated,    │
 * │ but product 456 cache should stay warm. Completely granular.               │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
enum QueryType
{
    case Listing;
    case Record;
}
