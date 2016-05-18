<?php
/**
 * Rubedo Core Composer Installer Plugin
 *
 * @category  Rubedo
 * @package   Rubedo.ComposerInstallerPlugin
 * @author    Guillaume Maïssa <g.maissa@novactive.com>
 * @copyright 2016 Novactive
 * @license   proprietary
 */

namespace Novactive\Rubedo\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

/**
 * Main class for Rubedo Core installation
 *
 * @category  Rubedo
 * @package   Rubedo.ComposerInstallerPlugin
 * @author    Guillaume Maïssa <g.maissa@novactive.com>
 * @copyright 2016 Novactive
 */
class CoreInstaller extends LibraryInstaller
{
    /**
     * Rubedo root dir
     *
     * @var string
     */
    protected $rubedoRootDir;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = '')
    {
        parent::__construct($io, $composer, $type);

        $options             = $composer->getPackage()->getExtra();
        $this->rubedoRootDir = isset($options['rubedo-root-dir']) ? rtrim($options['rubedo-root-dir'], '/') : '.';
    }

    /**
     * {@inheritdoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        if ($this->io->isVerbose()) {
            $this->io->write(
                sprintf(
                    "Install path for package %s is '%s'",
                    $package->getPrettyName(),
                    $this->rubedoRootDir
                )
            );
        }

        return $this->rubedoRootDir;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($packageType)
    {
        return $packageType === 'rubedo-core';
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return parent::isInstalled($repo, $package) && is_dir($this->getInstallPath($package). '/module/Rubedo');
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $installPath = $this->getInstallPath($package);
        $fileSystem  = new Filesystem();
        if (!is_dir($installPath) || $fileSystem->isDirEmpty($installPath)) {
            return parent::install($repo, $package);
        }

        $actualRootDir       = $this->rubedoRootDir;
        $this->rubedoRootDir = $this->generateTempDir();
        if ($this->io->isVerbose()) {
            $this->io->write("Installing in temporary directory.");
        }
        parent::install($repo, $package);

        $this->rubedoRootDir = $actualRootDir;

        $this->io->write('Rubedo Core new version : ' . $package->getVersion());
    }

    /**
     * Returns a unique temporary directory (full path).
     *
     * @return string
     */
    protected function generateTempDir()
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('_composer_rubedotmproot_');
        if ($this->io->isVerbose()) {
            $this->io->write("Temporary directory for Rubedo Core updates: $tmpDir");
        }

        return $tmpDir;
    }
}
