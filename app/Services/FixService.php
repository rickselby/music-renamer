<?php

namespace App\Services;

use getID3;
use getid3_writetags;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class FixService
{
    /** @var Filesystem */
    protected $source;

    /** @var getID3 */
    protected $id3;

    /** @var Command */
    protected $command;

    public function __construct(getID3 $getID3)
    {
        $this->source = \Storage::disk('source');
        $this->id3 = $getID3;
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
            $tags = $this->getTags($directory);
            $this->tryToFixTrackCount($directory, $tags);
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
                return explode('/', $trackNumber[0])[1];
            });

            if ($trackCounts->unique()->count() == 1 && $trackCounts->unique()->first() == '0') {
                $this->command->info('Adding track count to '.$directory.' disc '.$disc);

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
     * @param string $path
     * @param Collection $tags
     * @param $count
     */
    protected function updateTrackCount(string $path, Collection $tags, $count)
    {
        $number = explode('/', $tags['track_number'][0])[0];
        $tags = clone $tags;

        $tags->put('track_number', [$number.'/'.$count]);
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

        if (!$tagWriter->WriteTags()) {
            $this->command->error('Writing tags failed:');
            foreach($tagWriter->errors as $error) {
                $this->command->error($error);
            }
        }
    }
}
