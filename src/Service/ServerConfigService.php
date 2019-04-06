<?php
/*
 * (c) 2014, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle\Service;


use Symfony\Component\Cache\Simple\FilesystemCache;

class ServerConfigService
{
    const CONFIG_FILE = 'aerys_config.php';

    /**
     * @var string
     */
    private $cacheDirectory;

    public function __construct($cacheDirectory)
    {
        $this->cacheDirectory = $cacheDirectory;
    }

    public function getConfigFile()
    {
        $file = $this->getConfigFilePath();
        if (!file_exists($this->getConfigFilePath())) {

        }
        return $file;
    }

    public function getConfigFilePath()
    {
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . static::CONFIG_FILE;
    }
}