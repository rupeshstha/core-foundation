<?php

namespace CoreFoundation\Tests\Stubs;

use CoreFoundation\Services\ApplicationContext;

/**
 * OrderContext
 *
 * Holds all context data belonging to the Order domain.
 *
 * GUIDELINE:
 *  - Add a typed setter + getter pair for every piece of state.
 *  - Use set()    / get()       for data you want visible in logs.
 *  - Use setHidden() / getHidden() for sensitive data (tokens, PII).
 *  - Never call Context facade directly — always go through these methods.
 *  - Never share keys with another state class — the prefix handles isolation.
 */
class OrderContext extends ApplicationContext
{
    // Stored as: order.order_id
    // Stored as: order.status
    // Stored as: order.payment_token  (hidden — never logged)
    // Stored as: order.breadcrumbs    (stack — audit trail)

    protected function prefix(): string
    {
        return 'order';
    }

    // -------------------------------------------------------------------------
    // Public context — visible in logs
    // -------------------------------------------------------------------------

    public function setOrderId(int $id): static
    {
        return $this->set('order_id', $id);
    }

    public function orderId(): ?int
    {
        return $this->get('order_id');
    }

    public function setStatus(string $status): static
    {
        return $this->set('status', $status);
    }

    public function status(): ?string
    {
        return $this->get('status');
    }

    public function incrementItemsProcessed(int $by = 1): static
    {
        return $this->increment('items_processed', $by);
    }

    public function itemsProcessed(): int
    {
        return $this->get('items_processed', 0);
    }

    // -------------------------------------------------------------------------
    // Hidden context — sensitive, never written to logs
    // -------------------------------------------------------------------------

    public function setPaymentToken(string $token): static
    {
        return $this->setHidden('payment_token', $token);
    }

    public function paymentToken(): ?string
    {
        return $this->getHidden('payment_token');
    }

    // -------------------------------------------------------------------------
    // Stack — ordered audit trail
    // -------------------------------------------------------------------------

    public function pushBreadcrumb(string $step): static
    {
        return $this->push('breadcrumbs', $step);
    }

    public function breadcrumbs(): array
    {
        return $this->get('breadcrumbs', []);
    }
}

// =============================================================================
// USAGE EXAMPLES
// =============================================================================
//
// Basic:
//   $context = OrderContext::make()
//       ->setOrderId(42)
//       ->setStatus('pending')
//       ->setPaymentToken('tok_abc123');
//
//   $context->orderId();       // 42
//   $context->paymentToken();  // 'tok_abc123'  (not in logs)
//
// Fluent in a service:
//   OrderContext::make()
//       ->setOrderId($order->id)
//       ->setStatus($order->status)
//       ->pushBreadcrumb('payment_initiated')
//       ->pushBreadcrumb('payment_confirmed');
//
// Reading back anywhere in the same request or queued job:
//   $context = new OrderContext;
//   $context->orderId();   // still 42 — context is global within the process
//
// Scoped temporary state:
//   OrderContext::make()->scope(function (OrderContext $context) {
//       $context->setStatus('processing');
//       // ... temporary work ...
//   }); // status reverts to previous value after scope exits
//
// Debug snapshot (never use in production logging directly):
//   $context->snapshot();
//   // ['order_id' => 42, 'status' => 'pending', 'items_processed' => 0]
//
//   $context->snapshotHidden();
//   // ['payment_token' => 'tok_abc123']
//
// Flush all order context (e.g. between test cases or batch resets):
//   $context->flush();
