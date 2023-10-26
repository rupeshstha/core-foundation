<?php

namespace CoreFoundation\Jobs;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

abstract class BaseJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected bool $notify = false;
    protected ?string $notifiableUserName = null;

    public function __construct()
    {
        $this->notifiableUserName = Str::headline(Str::replace("Job", "", class_basename($this)));
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled()];
    }

    public function failed(Throwable $exception): void
    {
        // send notification
        $this->rollbackPreviousTransactions();
        $this->setLogs($exception);
    }

    final public function rollbackPreviousTransactions(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    final public function setLogs(Throwable $exception): void
    {
        Log::error(
            message: $exception->getMessage(),
            context: [
                "trace_line" => $exception->getLine(),
                "trace_file" => $exception->getFile(),
                "trace" => $exception->getTrace()
            ]
        );
    }
}
