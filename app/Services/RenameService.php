<?php

namespace App\Services;

use getID3;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class RenameService
{
    /** @var Filesystem */
    protected $source;

    /** @var Filesystem */
    protected $destination;

    /** @var getID3 */
    protected $id3;

    /** @var VerifyService */
    private $verifyService;

    public function __construct(getID3 $getID3, VerifyService $verifyService)
    {
        $this->source = \Storage::disk('source');
        $this->destination = \Storage::disk('destination');
        $this->id3 = $getID3;
        $this->verifyService = $verifyService;
    }

    public function renameAndVerify(Command $command)
    {
        $this->parseDirectory('/', $command, $this->destination);
    }

    public function renameWithoutVerify(Command $command)
    {
        $this->parseDirectory('/', $command, $this->destination, false);
    }

    public function renameLocally(Command $command)
    {
        $this->parseDirectory('/', $command, $this->source, false);
    }

    /**
     * Parse a single directory
     *
     * @param string $directory
     */
    protected function parseDirectory(string $directory, Command $command, Filesystem $destination, bool $verify = true)
    {
        foreach ($this->source->directories($directory) as $subDirectory) {
            $this->parseDirectory($subDirectory, $command, $destination, $verify);
        }

        if (count($this->source->files($directory))) {
            $tags = $this->getTags($directory);

            if (!$verify || $this->verifyService->verify($tags)) {
                $command->info('Moving "'.$directory.'"');
                $this->moveFiles($directory, $tags, $destination);
            } else {
                $command->error('Could not move "'.$directory.'"');
                $this->verifyService->getErrors()->each(function ($error) use ($command) {
                    $command->comment($error);
                });
            }
        } else {
            $command->info('Directory "'.$directory.'" is empty');
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

    /**
     * Move files from the given directory to the given destination
     *
     * @param string $directory
     * @param Collection $files
     * @param Filesystem $destination
     */
    protected function moveFiles(string $directory, Collection $files, Filesystem $destination)
    {
        $discNumbers = ($files->pluck('part_of_a_set')->unique()->count() != 1);
        $differentArtists = ($files->pluck('artist')->unique()->count() != 1);

        $files->each(function (Collection $tags, $file) use ($directory, $discNumbers, $differentArtists, $destination) {
            // Build the source path
            $sourcePath = $directory . DIRECTORY_SEPARATOR . $file;
            $destinationPath = $this->getDestinationPath($tags, $differentArtists, $discNumbers);

            // Don't copy and delete if source and destination are the same
            if ($this->source->path($sourcePath) != $destination->path($destinationPath)) {
                // Copy the file
                $destination->put($destinationPath, $this->source->readStream($sourcePath));

                // Remove the source, if the files are not identical
                $this->source->delete($sourcePath);
            }
        });

        $this->source->deleteDir($directory);
    }

    /**
     * Generate the destination filename
     *
     * @param Collection $tags
     * @param bool $differentArtists
     * @param bool $discNumbers
     *
     * @return string
     */
    protected function getDestinationPath(Collection $tags, bool $differentArtists, bool $discNumbers)
    {
        /* [Artist|AlbumArtist]Name/AlbumName/[DiscNumber - ]TrackNumber - [ArtistName - ]TrackName.ext */

        $destinationPath = '';

        if ($differentArtists) {
            $destinationPath .= $tags->get('band')[0];
        } else {
            $destinationPath .= $tags->get('artist')[0];
        }

        $destinationPath .= DIRECTORY_SEPARATOR . $tags->get('album')[0] . DIRECTORY_SEPARATOR;

        if ($discNumbers) {
            $destinationPath .= $this->getDiscNumber($tags).' - ';
        }

        $destinationPath .= $this->getTrackNumber($tags). ' - ';

        if ($differentArtists) {
            $destinationPath .= $tags->get('artist')[0] . ' - ';
        }

        $destinationPath .= $tags->get('title')[0];

        $destinationPath .= '.mp3';

        return $destinationPath;
    }

    protected function getDiscNumber(Collection $tags)
    {
        return explode('/', $tags->get('part_of_a_set')[0])[0];
    }

    protected function getTrackNumber(Collection $tags)
    {
        return str_pad(
            explode('/', $tags->get('track_number')[0])[0],
            2,
            '0',
            STR_PAD_LEFT
        );
    }
}
