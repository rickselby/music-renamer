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

        return $this->errors->count() ? true : false;
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
}
