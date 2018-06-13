<?php

namespace App\Console\Commands;

use App\Services\RenameService;
use Illuminate\Console\Command;

class RenameNoVerifyCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = "rename:noverify";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Rename music files without verifying";

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
        $this->renameService->renameWithoutVerify($this);
    }
}
