<?php

namespace App\Console\Commands;

use App\Services\RenameService;
use Illuminate\Console\Command;

class RenameCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = "rename";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Rename music files";

    /** @var RenameService */
    private $renameService;

    public function __construct(RenameService $renameService)
    {
        parent::__construct();
        $this->renameService = $renameService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->renameService->rename($this);
    }
}
