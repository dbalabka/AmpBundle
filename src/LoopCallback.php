<?php
/**
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle;


interface LoopCallback
{
    /**
     * Method will be called in loop context (@see Loop::run())
     *
     * @return mixed
     */
    public function __invoke();
}
