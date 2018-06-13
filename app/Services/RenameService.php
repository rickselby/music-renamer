<?php

namespace App\Services;

use \Illuminate\Contracts\Filesystem\Filesystem;
use \getID3;
use Illuminate\Support\Collection;

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

        if (count($this->source->files($directory))) {
            $tags = $this->getTags($directory);

            if ($this->verifyService->verify($tags)) {
                dd($directory, 'yup');
                // Move the files to the destination
            } else {
                // Report the error
            }
        } else {
            // Note empty directory
        }
    }

    /**
     * Get the id3 tags for all files in a directory
     *
     * @param string $directory
     *
     * @return Collection
     */
    protected function getTags($directory)
    {
        $tags = collect();
        foreach ($this->source->files($directory) as $file) {
            $tags->put(
                basename($file),
                collect($this->id3->analyze($this->source->path($file))['tags']['id3v2'])
            );
        }
        return $tags;
    }
}
