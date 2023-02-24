<?php

namespace Programm011\Actiondata;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

interface ActionDataContract
{
    /**
     * @param Request $request
     *
     * @return mixed
     */
    public static function createFromRequest(Request $request): mixed;

    /**
     * @param bool $silent
     *
     * @return bool
     * @throws ValidationException
     */
    public function validate(bool $silent = true): bool;
}
