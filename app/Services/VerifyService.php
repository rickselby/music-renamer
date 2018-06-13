<?php

namespace App\Services;

class VerifyService
{
    /**
     * Verify a given directory can be renamed
     *
     * @param string $directory
     *
     * @return boolean
     */
    public function verify(string $directory)
    {
        // TODO
    }

    /**
     * Confirm that each file in a directory has the same Album Name and Album Artist
     *
     * @param string $directory
     *
     * @return bool
     *
    protected function checkFilesHaveSameAlbumAndAlbumArtist(string $directory)
    {
        $album = null;
        $artist = null;

        foreach ($this->source->files($directory) as $file) {

            $sth = $this->id3->analyze($this->source->path($file));

            $id3 = $sth['id3v2'];
            unset($id3['APIC']);

            dd(
                array_keys($sth)
            );

            $tags = $this->id3->analyze($this->source->path($file))['tags']['id3v2'];

            if ($album) {
                if ($album != $tags['album']) {
                    return false;
                }
            } else {
                $album = $tags['album'];
            }

            if ($artist) {
                if ($artist != $this->getArtist($tags)) {
                    return false;
                }
            } else {
                $artist = $this->getArtist($tags);
            }
        }

        return true;
    }
     */

    /**
     * Get the artist from tags - prefer the album artist
     *
     * @param array $tags
     *
     * @return string
     *
    protected function getArtist(array $tags)
    {
        return $tags['band'] ?? $tags['artist'];
    }
     */
}