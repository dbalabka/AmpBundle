<?php
/**
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle\Tests\Functional;

use Amp\AmpBundle\DefaultLoopCallback;

class DefaultLoopCallbackTestDecorator extends DefaultLoopCallback
{
    private static $promiseHandler;

    public static function setPromiseHandler(callable $promiseHandler)
    {
        static::$promiseHandler = $promiseHandler;
    }

    public function __invoke()
    {
        yield from parent::__invoke();
        yield from (static::$promiseHandler)();
    }
}
