<?php
/*
 * (c) 2014, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle\Tests\Functional;

use Amp\AmpBundle\Tests\Functional\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase as BaseKernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class WebTestCase extends BaseKernelTestCase
{
    protected static function getKernelClass()
    {
        opcache_reset();
//        require_once __DIR__.'/App/Kernel.php';
        return Kernel::class;
    }

    public static function setUpBeforeClass()
    {
        static::deleteTmpDir();
    }

    public static function tearDownAfterClass()
    {
        static::deleteTmpDir();
    }

    protected static function deleteTmpDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.static::getVarDir())) {
            return;
        }
        $fs = new Filesystem();
        $fs->remove($dir);
    }

    protected static function createKernel(array $options = [])
    {
        $class = self::getKernelClass();
        if (!isset($options['test_case'])) {
            throw new \InvalidArgumentException('The option "test_case" must be set.');
        }
        return new $class(
            static::getVarDir(),
            $options['test_case'],
            isset($options['root_config']) ? $options['root_config'] : 'config.yaml',
            isset($options['environment']) ? $options['environment'] : strtolower(static::getVarDir().$options['test_case']),
            isset($options['debug']) ? $options['debug'] : true
        );
    }

    public static function createKernelForApplication(array $options = [])
    {
        return static::createKernel($options);
    }

    protected static function getVarDir()
    {
        return substr(strrchr(get_called_class(), '\\'), 1);
    }
}
