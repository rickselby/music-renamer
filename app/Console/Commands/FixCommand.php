<?php

namespace App\Console\Commands;

use App\Services\FixService;
use Illuminate\Console\Command;

class FixCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = "fix";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Fix simple issues with files";

    /** @var FixService */
    private $fixService;

    public function __construct(FixService $fixService)
    {
        parent::__construct();
        $this->fixService = $fixService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->fixService->fix($this);
    }
}
