<?php

namespace App\Services;

use getid3_writetags;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class FixService
{
    use GetTagsTrait;

    /** @var Filesystem */
    protected $source;

    /** @var Command */
    protected $command;

    public function __construct()
    {
        $this->source = \Storage::disk('source');
    }

    /**
     * Try to fix some simple issues
     *
     * @param Command $command
     */
    public function fix(Command $command)
    {
        $this->command = $command;
        $this->parseDirectory('/');
    }

    /**
     * Work through each directory
     *
     * @param string $directory
     */
    protected function parseDirectory(string $directory)
    {
        foreach ($this->source->directories($directory) as $subDirectory) {
            $this->parseDirectory($subDirectory);
        }

        if (count($this->source->files($directory))) {
            $tags = $this->getTags($this->source, $directory);
            $this->tryToFixTrackCount($directory, $tags);
            $this->tryToFixDiscCount($directory, $tags);
        }
    }

    /**
     * Get each disk in a directory
     *
     * @param Collection $tags
     * @param callable $callable
     */
    protected function eachDisc(Collection $tags, callable $callable)
    {
        $tags->pluck('part_of_a_set')->unique()->each($callable);
    }

    /**
     * See if we can quickly fix the track count for a directory
     *
     * @param string $directory
     * @param Collection $tags
     */
    protected function tryToFixTrackCount(string $directory, Collection $tags)
    {
        $this->eachDisc($tags, function ($disc) use ($tags, $directory) {
            $discTracks = $tags->where('part_of_a_set', $disc);

            $trackCounts = $discTracks->pluck('track_number')->map(function ($trackNumber) {
                return stristr($trackNumber[0], '/') ? explode('/', $trackNumber[0])[1] : 0;
            });

            if ($trackCounts->unique()->count() == 1 && $trackCounts->unique()->first() == '0') {
                $this->command->info('Adding track count to '.$directory.' disc '.$disc[0]);

                $discTracks->each(function ($trackTags, $filename) use ($directory, $discTracks) {
                    $this->updateTrackCount(
                        $this->source->path($directory.DIRECTORY_SEPARATOR.$filename),
                        $trackTags,
                        $discTracks->count()
                    );
                });
            }
        });
    }

    /**
     * Update the track count only for a file
     *
     * @param string $path Full path to the file
     * @param Collection $tags Tags for the file
     * @param int $count New track count
     */
    protected function updateTrackCount(string $path, Collection $tags, $count)
    {
        $number = explode('/', $tags['track_number'][0])[0];
        $tags->put('track_number', [$number.'/'.$count]);
        $this->updateTags($path, $tags);
    }

    /**
     * See if we can quickly fix the track count for a directory
     *
     * @param string $directory
     * @param Collection $tags
     */
    protected function tryToFixDiscCount(string $directory, Collection $tags)
    {
        $discNumbers = $tags->pluck('part_of_a_set')->map(function ($discNumber) {
            return explode('/', $discNumber[0])[0];
        });

        if ($discNumbers->unique()->count() == 1 && $discNumbers->unique()->first() == '0') {
            $this->command->info('Updating disc number for '.$directory);

            $tags->each(function ($trackTags, $filename) use ($directory) {
                $this->updateSingleDiscNumber(
                    $this->source->path($directory.DIRECTORY_SEPARATOR.$filename),
                    $trackTags
                );
            });
        } else {

            $discCounts = $tags->pluck('part_of_a_set')->map(function ($discNumber) {
                return stristr('/', $discNumber[0]) ? explode('/', $discNumber[0])[1] : 0;
            });

            $discCount = $tags->pluck('part_of_a_set')->unique()->count();

            if ($discCounts->unique()->count() == 1 && $discCounts->unique()->first() == '0') {
                $this->command->info('Adding disc count to ' . $directory);

                $tags->each(function ($trackTags, $filename) use ($directory, $discCount) {
                    $this->updateDiscCount(
                        $this->source->path($directory . DIRECTORY_SEPARATOR . $filename),
                        $trackTags,
                        $discCount
                    );
                });
            }
        }
    }

    /**
     * Update the disc count only for a file
     *
     * @param string $path Full path to the file
     * @param Collection $tags Tags for the file
     * @param int $count New disc count
     */
    protected function updateDiscCount(string $path, Collection $tags, $count)
    {
        $number = explode('/', $tags['part_of_a_set'][0])[0];
        $tags->put('part_of_a_set', [$number.'/'.str_pad($count, 2, '0', STR_PAD_LEFT)]);
        $this->updateTags($path, $tags);
    }

    /**
     * Update the disc count only for a file
     *
     * @param string $path Full path to the file
     * @param Collection $tags Tags for the file
     * @param int $count New disc count
     */
    protected function updateSingleDiscNumber(string $path, Collection $tags)
    {
        $tags->put('part_of_a_set', ['1/1']);
        $this->updateTags($path, $tags);
    }

    /**
     * Update tags for a file
     *
     * @param string $path
     * @param Collection $tags
     */
    private function updateTags(string $path, Collection $tags)
    {
        $tagWriter = new getid3_writetags();
        $tagWriter->tagformats = ['id3v2.4'];
        $tagWriter->tag_encoding = 'UTF-8';
        $tagWriter->filename = $path;
        $tagWriter->tag_data = $tags->toArray();

        unset($tagWriter->tag_data['text']);
        unset($tagWriter->tag_data['comment']);

        if (!$tagWriter->WriteTags()) {
            $this->command->error('Writing tags failed:');
            foreach($tagWriter->errors as $error) {
                $this->command->error($error);
            }
        }
    }
}
