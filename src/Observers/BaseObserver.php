<?php

namespace CoreFoundation\Observers;

/**
 * BaseObserver
 *
 * Abstract Eloquent observer base. All lifecycle methods are no-op by default —
 * override only the events you need to react to.
 *
 * USAGE:
 *
 *   class OrderObserver extends BaseObserver
 *   {
 *       public function created(mixed $model): void
 *       {
 *           // runs after an Order is persisted for the first time
 *       }
 *
 *       public function deleting(mixed $model): void
 *       {
 *           // runs before an Order is deleted — return false to cancel
 *       }
 *   }
 *
 * REGISTRATION (in module ServiceProvider::boot()):
 *
 *   Order::observe(OrderObserver::class);
 *
 * CANCELLING AN OPERATION:
 *
 * The before-event methods (creating, updating, saving, deleting, restoring,
 * forceDeleting) can cancel the operation by returning false:
 *
 *   public function deleting(mixed $model): bool
 *   {
 *       return $model->canBeDeleted(); // false cancels the delete
 *   }
 *
 * PHPDoc GENERIC TYPES (for IDE type safety — not enforced at runtime):
 *
 * Declare @extends on your concrete observer so IDEs resolve the correct type:
 *
 *   @extends BaseObserver<Order>
 *
 * Then PHpStorm / Intelephense will infer that $model is Order in your methods.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class BaseObserver
{
    // =========================================================================
    // Creating / Created
    // =========================================================================

    /**
     * Before the model is persisted for the first time.
     * Return false to cancel the creation.
     *
     * @param  TModel  $model
     */
    public function creating(mixed $model): void {}

    /**
     * After the model has been persisted for the first time.
     *
     * @param  TModel  $model
     */
    public function created(mixed $model): void {}

    // =========================================================================
    // Updating / Updated
    // =========================================================================

    /**
     * Before changes are persisted to an existing record.
     * Return false to cancel the update.
     *
     * @param  TModel  $model
     */
    public function updating(mixed $model): void {}

    /**
     * After changes have been persisted to an existing record.
     *
     * @param  TModel  $model
     */
    public function updated(mixed $model): void {}

    // =========================================================================
    // Saving / Saved (fires on both create and update)
    // =========================================================================

    /**
     * Before a create or update is persisted.
     * Return false to cancel the save.
     *
     * @param  TModel  $model
     */
    public function saving(mixed $model): void {}

    /**
     * After a create or update has been persisted.
     *
     * @param  TModel  $model
     */
    public function saved(mixed $model): void {}

    // =========================================================================
    // Deleting / Deleted
    // =========================================================================

    /**
     * Before a record is deleted.
     * Return false to cancel the deletion.
     *
     * @param  TModel  $model
     */
    public function deleting(mixed $model): void {}

    /**
     * After a record has been deleted.
     *
     * @param  TModel  $model
     */
    public function deleted(mixed $model): void {}

    // =========================================================================
    // Soft-delete lifecycle — only fires when the model uses SoftDeletes
    // =========================================================================

    /**
     * Before a soft-deleted record is restored.
     * Return false to cancel the restoration.
     *
     * @param  TModel  $model
     */
    public function restoring(mixed $model): void {}

    /**
     * After a soft-deleted record has been restored.
     *
     * @param  TModel  $model
     */
    public function restored(mixed $model): void {}

    /**
     * Before a record is permanently deleted from the database.
     * Return false to cancel.
     *
     * @param  TModel  $model
     */
    public function forceDeleting(mixed $model): void {}

    /**
     * After a record has been permanently deleted from the database.
     *
     * @param  TModel  $model
     */
    public function forceDeleted(mixed $model): void {}

    // =========================================================================
    // Replicating
    // =========================================================================

    /**
     * When a model instance is being replicated via $model->replicate().
     *
     * @param  TModel  $model
     */
    public function replicating(mixed $model): void {}
}
