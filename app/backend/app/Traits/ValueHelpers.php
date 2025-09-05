<?php

namespace App\Traits;

trait ValueHelpers
{
    private function nullishValue($value)
    {
        return $value === '' || $value === null ? null : $value;
    }

    private function defaultValue($value)
    {
        return $value === '' || $value === null ? -1 : $value;
    }
}