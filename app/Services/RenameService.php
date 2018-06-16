<?php

namespace App\Services;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class RenameService
{
    use GetTagsTrait;

    /** @var Filesystem */
    protected $source;

    /** @var Filesystem */
    protected $destination;

    /** @var VerifyService */
    private $verifyService;

    /** @var Command */
    private $command;

    public function __construct(VerifyService $verifyService)
    {
        $this->source = \Storage::disk('source');
        $this->destination = \Storage::disk('destination');
        $this->verifyService = $verifyService;
    }

    public function renameAndVerify(Command $command)
    {
        $this->setCommand($command);
        $this->parseDirectory('/', $this->destination);
    }

    public function renameWithoutVerify(Command $command)
    {
        $this->setCommand($command);
        $this->parseDirectory('/', $this->destination, false);
    }

    public function renameLocally(Command $command)
    {
        $this->setCommand($command);
        $this->parseDirectory('/', $this->source, false);
    }

    private function setCommand(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Parse a single directory
     *
     * @param string $directory
     */
    protected function parseDirectory(string $directory, Filesystem $destination, bool $verify = true)
    {
        foreach ($this->source->directories($directory) as $subDirectory) {
            $this->parseDirectory($subDirectory, $destination, $verify);
        }

        if (count($this->source->files($directory))) {
            $tags = $this->getTags($this->source, $directory);

            if (!$verify || $this->verifyService->verify($tags)) {
                $this->command->info('Moving "'.$directory.'"');
                $this->moveFiles($directory, $tags, $destination);
            } else {
                $this->command->error('Could not move "'.$directory.'"');
                $this->verifyService->getErrors()->each(function ($error) {
                    $this->command->comment($error);
                });
            }
        }

        // Only delete the directory if it's truly empty and not the root
        if (empty($this->source->allFiles($directory))
            && empty($this->source->allDirectories($directory))
            && $directory != '/')
        {
            $this->command->info('Directory "'.$directory.'" is empty; removing');
            $this->source->deleteDir($directory);
        }
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
        // Check if there are different disc numbers in the directory
        $discNumbers = ($files->pluck('part_of_a_set')->unique()->count() != 1);

        // Check if there are album artists, and if they are different to the track artists
        $differentArtists =
            $files->filter(function (Collection $tag) {
                return $tag->get('band') && ($tag->get('band') != $tag->get('artist'));
            })->count() > 0;

        $files->each(function (Collection $tags, $file) use ($directory, $discNumbers, $differentArtists, $destination) {
            // Build the source path
            $sourcePath = $directory . DIRECTORY_SEPARATOR . $file;
            $destinationPath = $this->getDestinationPath($tags, $differentArtists, $discNumbers);

            // Don't copy and delete if source and destination are the same
            if ($this->source->path($sourcePath) != $destination->path($destinationPath)) {
                // Copy the file
                $destination->put($destinationPath, $this->source->readStream($sourcePath));

                $this->source->delete($sourcePath);
            }
        });
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

        return
            // First folder - album artist or artist
            $this->sanitisePathPath(
                $differentArtists ? $tags->get('band')[0] : $tags->get('artist')[0]
            ). DIRECTORY_SEPARATOR
            // Subfolder - album name
            . $this->sanitisePathPath($tags->get('album')[0]) . DIRECTORY_SEPARATOR
            // Disc number first, if required
            . ($discNumbers ? $this->getDiscNumber($tags).' - ' : '')
            // Then track number
            . $this->getTrackNumber($tags). ' - '
            // If the album has different artists, list the artist next
            . ($differentArtists ? $this->sanitisePathPath($tags->get('artist')[0]) . ' - ' : '')
            // Finally the track name
            . $this->sanitisePathPath($tags->get('title')[0])
            . '.mp3'
        ;
    }

    /**
     * Get the disc number for the given file
     *
     * @param Collection $tag
     *
     * @return mixed
     */
    protected function getDiscNumber(Collection $tag)
    {
        return explode('/', $tag->get('part_of_a_set')[0])[0];
    }

    /**
     * Get the track number for the given file
     *
     * @param Collection $tags
     *
     * @return string
     */
    protected function getTrackNumber(Collection $tags)
    {
        return str_pad(
            explode('/', $tags->get('track_number')[0])[0],
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Sanitise a directory / file name
     *
     * @param string $string
     *
     * @return string
     */
    protected function sanitisePathPath(string $string)
    {
        $string = str_replace('/', '-', $string);
        $string = str_replace(['*', '$', '#', '%', '^'], '_', $string);
        $string = str_replace(['&', '+'], 'and', $string);
        $string = str_replace(['\'', '"', '!', '?', '’'], '', $string);

        return $string;
    }
}
