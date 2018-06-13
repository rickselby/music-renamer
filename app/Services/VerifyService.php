<?php

namespace App\Services;

use Illuminate\Support\Collection;

class VerifyService
{
    /** @var Collection */
    protected $errors;

    /** @var Collection */
    protected $tags;

    /**
     * Verify a given directory can be renamed
     *
     * @param Collection $tags
     *
     * @return boolean
     */
    public function verify(Collection $tags)
    {
        $this->errors = collect();
        $this->tags = $tags;

        $this->verifyAllTagsHaveField('artist');
        $this->verifyAllTagsHaveField('album');
        $this->verifyAllTagsHaveField('title');

        $this->verifyAllFieldsAreTheSame('album');

        if ($this->hasField('band')) {
            // Is a 'Various Artists' album, so verify that's all the same
            // Could have different artists per track
            $this->verifyAllTagsHaveField('band');
            $this->verifyAllFieldsAreTheSame('band');
        } else {
            // Single artist album
            $this->verifyAllFieldsAreTheSame('artist');
        }

        $this->verifyTrackCountCorrect();
        $this->verifyTrackNumbersCorrect();

        $this->verifyDiscs();

        return $this->errors->count() ? false : true;
    }

    /**
     * Get a list of errors from the last verification
     * @return Collection
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get a count of the total number of files
     *
     * @return int
     */
    protected function numFiles()
    {
        return $this->tags->count();
    }

    /**
     * Check all files have the given field
     *
     * @param string $field
     */
    protected function verifyAllTagsHaveField(string $field)
    {
        if ($this->tags->pluck($field)->filter()->count() != $this->numFiles()) {
            $this->errors->push('Not all files have the "'.$field.'" field');
        }
    }

    /**
     * Check all of the given field are the same
     *
     * @param string $field
     */
    protected function verifyAllFieldsAreTheSame(string $field)
    {
        if ($this->tags->pluck($field)->unique()->count() != 1) {
            $this->errors->push('"'.$field.'" should be unique, but is not');
        }
    }

    /**
     * Check if any files have the given field
     *
     * @param $field
     *
     * @return bool
     */
    protected function hasField($field)
    {
        return $this->tags->pluck($field)->filter()->count() ? true : false;
    }

    protected function verifyTrackCountCorrect()
    {
        $this->eachDisc(function ($disc) {
            $discTracks = $this->tags->where('part_of_a_set', $disc);

            $trackCounts = $discTracks->pluck('track_number')->map(function ($trackNumber) {
                return explode('/', $trackNumber[0])[1];
            });

            if ($trackCounts->unique()->count() != 1) {
                $this->errors->push('Track counts are different between tracks');
            }

            $trackCounts->each(function ($trackCount) use ($discTracks) {
                if ($trackCount != $discTracks->count()) {
                    $this->errors->push('Track count is incorrect');
                }
            });
        });
    }

    protected function verifyTrackNumbersCorrect()
    {
        $this->eachDisc(function ($disc) {
            $discTracks = $this->tags->where('part_of_a_set', $disc);

            $trackNumbers = $discTracks->pluck('track_number')->map(function ($trackNumber) {
                return explode('/', $trackNumber[0])[0];
            });

            if ($trackNumbers->unique()->count() != $trackNumbers->count()) {
                $this->errors->push('There are duplicate track numbers');
            }

            if ($trackNumbers->max() != $discTracks->count()) {
                $this->errors->push('Track numbers are incorrect');
            }
        });
    }

    protected function eachDisc(callable $callable)
    {
        $this->tags->pluck('part_of_a_set')->unique()->each($callable);
    }

    protected function verifyDiscs()
    {
        $discs = $this->tags->pluck('part_of_a_set')->unique();

        $this->verifyDiscCounts($discs);
        $this->verifyDiscNumbers($discs);
    }

    protected function verifyDiscCounts(Collection $discs)
    {
        $discCounts = $discs->map(function ($discNumber) {
            return explode('/', $discNumber[0])[1];
        });

        if ($discCounts->unique()->count() != 1) {
            $this->errors->push('Disc counts are different between tracks');
        }

        $discCounts->each(function ($trackCount) use ($discs) {
            if ($trackCount != $discs->count()) {
                $this->errors->push('Disc count is incorrect');
            }
        });
    }

    protected function verifyDiscNumbers(Collection $discs)
    {
        $discNumbers = $discs->map(function ($discNumber) {
            return explode('/', $discNumber[0])[0];
        });


        if ($discNumbers->unique()->count() != $discNumbers->count()) {
            $this->errors->push('There are duplicate disc numbers');
        }

        if ($discNumbers->max() != $discNumbers->count()) {
            $this->errors->push('Disc numbers are incorrect');
        }
    }
}
