<?php

namespace CoreFoundation\Console;

use CoreFoundation\Services\Utils\File;
use Illuminate\Console\Command;
use Process;
use RuntimeException;

class WatchQueryCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'core:foundation:query-watch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate database test factories for models';

    /**
     * The file instance, if any.
     */
    protected ?File $file = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->check();

        $this->info("Analyzing query...⏳");
        // QA stands for query analyzer.
        $this->file = new File(storage_path('qa/'.uniqid().'.qa'));
        $this->file->create();

        $process = Process::path(storage_path('logs'))
            ->run("tail -f query.log");// this wont work


        dd("here", $process->output());
    }

    public static function check(): void
    {
        if (! function_exists('pcntl_fork')) {
            throw new RuntimeException('The [pcntl] extension is required to run Pail.');
        }
    }
}
