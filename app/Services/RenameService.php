<?php

namespace App\Services;

use \Illuminate\Contracts\Filesystem\Filesystem;

class RenameService
{
    /** @var Filesystem */
    protected $source;

    public function __construct()
    {
        $this->source = \Storage::disk('source');
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

        if ($this->checkFilesHaveSameAlbumAndAlbumArtist($directory)) {
            // Move the files to the destination
        } else {
            // Report the error
        }
    }

    /**
     * Confirm that each file in a directory has the same Album Name and Album Artist
     *
     * @param string $directory
     *
     * @return bool
     */
    protected function checkFilesHaveSameAlbumAndAlbumArtist(string $directory)
    {
        foreach ($this->source->files($directory) as $file) {
            // TODO
        }

        return false;
    }
}
