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
        $this->parseDirectory('/', $command);
    }

    /**
     * Parse a single directory
     *
     * @param string $directory
     */
    protected function parseDirectory(string $directory, Command $command)
    {
        foreach ($this->source->directories($directory) as $subDirectory) {
            $this->parseDirectory($subDirectory, $command);
        }

        if (count($this->source->files($directory))) {
            $tags = $this->getTags($directory);

            if ($this->verifyService->verify($tags)) {
                $command->info('Moving "'.$directory.'"');
                $this->moveFiles($directory, $tags);
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

    protected function moveFiles(string $directory, Collection $files)
    {
        $discNumbers = ($files->pluck('part_of_a_set')->unique()->count() != 1);
        $differentArtists = ($files->pluck('artist')->unique()->count() != 1);

        $files->each(function (Collection $tags, $file) use ($directory, $discNumbers, $differentArtists) {
            // Build the source path
            $sourcePath = $directory . DIRECTORY_SEPARATOR . $file;

            // Copy the file
            $this->destination->put(
                $this->getDestinationPath($tags, $differentArtists, $discNumbers),
                $this->source->readStream($sourcePath)
            );

            // Remove the source
            $this->source->delete($sourcePath);
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
