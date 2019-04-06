<?php
/**
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle\Tests\Functional\App\Controller;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class IndexController
{

    /**
     * @Route("/")
     */
    public function index()
    {
        return new JsonResponse(["ok"]);
    }
}
