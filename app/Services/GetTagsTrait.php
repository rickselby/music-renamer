<?php

namespace App\Services;

use getID3;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

trait GetTagsTrait
{
    /**
     * Get the id3 tags for all files in a directory
     *
     * @param string $directory
     *
     * @return Collection
     */
    protected function getTags(Filesystem $source, string $directory)
    {
        $tags = collect();
        foreach ($source->files($directory) as $file) {
            $tags->put(
                basename($file),
                collect($this->getTagsForFile($source->path($file)))
            );
        }
        return $tags;
    }

    private function getTagsForFile($path)
    {
        $id3 = new getID3();
        $tags = $id3->analyze($path)['tags'];
        if (isset($tags['quicktime'])) {
            $tags['id3v2'] = $this->renameQuickTime($tags['quicktime']);
        }
        return $tags['id3v2'];
    }

    private function renameQuickTime($tags)
    {
        $tags['part_of_a_set'] = $tags['disc_number'];
        if (isset($tags['album_artist'])) {
            $tags['band'] = $tags['album_artist'];
        }

        return $tags;
    }
}
