<?php

namespace CoreFoundation\Traits;

use Illuminate\Support\Facades\Schema;

trait ModelFillables
{
    public function getFillable(): array
    {
        return Schema::getColumnListing($this->getTable());
    }

    private function initializeAutoFillableTrait(): void
    {
        $this->fillable = $this->getFillable();
    }
}
