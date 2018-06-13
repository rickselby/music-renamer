<?php

namespace App\Services;

use \Illuminate\Contracts\Filesystem\Filesystem;
use \getID3;

class RenameService
{
    /** @var Filesystem */
    protected $source;

    /** @var getID3 */
    protected $id3;

    /** @var VerifyService */
    private $verifyService;

    public function __construct(getID3 $getID3, VerifyService $verifyService)
    {
        $this->source = \Storage::disk('source');
        $this->id3 = $getID3;
        $this->verifyService = $verifyService;
    }

    public function rename()
    {
        $this->parseDirectory('/');
    }

    /**
     * Parse a single directory
     *
     * @param string $directory
     */
    protected function parseDirectory(string $directory)
    {
        foreach ($this->source->directories($directory) as $subDirectory) {
            $this->parseDirectory($subDirectory);
        }

        if ($this->verifyService->verify($directory)) {
            dd($directory, 'yup');
            // Move the files to the destination
        } else {
            // Report the error
        }
    }
}
