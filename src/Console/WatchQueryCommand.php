<?php

namespace CoreFoundation\Console;

use Process;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use CoreFoundation\Services\Utils\File;
use CoreFoundation\Services\Utils\CliPrinter;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessSignaledException;

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

    public function handle(

    ): void {
        $this->check();

        $this->info("Analyzing query...⏳");

        // QA stands for query analyzer.
        $this->file = new File(storage_path('query-analyzer/'.uniqid().'.qa'));
        $this->file->create();

        // Checks application signals.
        $this->trap([SIGINT, SIGTERM], fn () => $this->file->destroy());

        try {
            $this->process($this->file, $this->output, $this->laravel->basePath(), $this->options());
            dd("asdd");
        } catch (ProcessSignaledException $e) {
            if (in_array($e->getSignal(), [SIGINT, SIGTERM], true)) {
                $this->newLine();
            }
        } catch (ProcessTimedOutException $e) {
            $this->components->info('Maximum execution time exceeded.');
        } finally {
            $this->file?->destroy();
        }
        dd("asd");
        // $data = "";
        // $process = Process::path(storage_path('logs'))
        //     ->run("tail -f query.log", function ($output, $extra) use (&$data) {
        //         $data = $extra;
        //         return $data;
        //     });// this wont work

        dd("here",);
    }

    public static function check(): void
    {
        if (! function_exists('pcntl_fork')) {
            throw new RuntimeException('The [pcntl] extension is required to run Pail.');
        }
    }

    /**
     * Creates a new instance of the process factory.
     */
    public function process(File $file, OutputInterface $output, string $basePath, Options|array $options = []): void
    {
        $printer = new CliPrinter($output, $basePath);

        $remainingBuffer = '';

        Process::timeout(3600)
            ->tty(false)
            ->run(
                $this->cliCommand($file),
                function (string $type, string $buffer) use ($options, $printer, &$remainingBuffer) {
                    $lines = Str::of($buffer)->explode("\n");

                    if ($remainingBuffer !== '' && isset($lines[0])) {
                        $lines[0] = $remainingBuffer.$lines[0];
                        $remainingBuffer = '';
                    }

                    if ($lines->last() === '') {
                        $lines = $lines->slice(0, -1);
                    } elseif (! str_ends_with((string) $lines->last(), "\n")) {
                        $remainingBuffer = $lines->pop();
                    }
                    $lines
                        ->filter(fn (string $line) => $line !== '')
                        ->map(fn (string $line) => MessageLogged::fromJson($line))
                        ->filter(fn (MessageLogged $messageLogged) => $options->accepts($messageLogged))
                        ->each(fn (MessageLogged $messageLogged) => $printer->print($messageLogged));
                }
            );
    }

    /**
     * Returns the raw command.
     */
    protected function cliCommand(File $file): string
    {
        return '\\tail -F "'.$file->__toString().'"';
    }

    /**
     * Handles the object destruction.
     */
    public function __destruct()
    {
        if ($this->file) {
            $this->file->destroy();
        }
    }
}
