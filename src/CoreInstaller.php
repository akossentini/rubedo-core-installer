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
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;

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
     * @var string
     */
    protected $rubedoRootDir;

    /**
     * Helper used to execute processes on the command line
     * @var ProcessExecutor
     */
    protected $process;

    /**
     * Filesystem manipulation helper
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = '')
    {
        parent::__construct($io, $composer, $type);

        $options             = $composer->getPackage()->getExtra();
        $this->rubedoRootDir = isset($options['rubedo-root-dir']) ? rtrim($options['rubedo-root-dir'], '/') : '.';
        $this->process       = new ProcessExecutor($io);
        $this->fileSystem    = new Filesystem();
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
        $fileSystem  = $this->fileSystem;
        if (!is_dir($installPath) || $fileSystem->isDirEmpty($installPath)) {
            return parent::install($repo, $package);
        }

        $actualRootDir       = $this->rubedoRootDir;
        $this->rubedoRootDir = $this->generateTempDir();
        if ($this->io->isVerbose()) {
            $this->io->write("Installing in temporary directory.");
        }
        parent::install($repo, $package);

        foreach ($repo->getPackages() as $installedPkg) {
            if ($installedPkg->getName() == $package->getName()) {
                $oldPkg = $installedPkg;
            }
        }

        $removedFiles = $this->getRemovedFiles($package, $oldPkg);
        $this->cleanTempDir($fileSystem);

        if ($this->io->isVerbose()) {
            $this->io->write("Updating new code over existing installation.");
        }

        $fileSystem->copyThenRemove($this->rubedoRootDir, $actualRootDir);

        if (method_exists($this,'removeBinaries')) {
            $this->removeBinaries($package);
        } else {
            $this->binaryInstaller->removeBinaries($package);
        }

        $this->rubedoRootDir = $actualRootDir;

        if (method_exists($this,'installBinaries')) {
            $this->installBinaries($package);
        } else {
            $this->binaryInstaller->installBinaries($package, $this->getInstallPath($package));
        }

        $this->deleteRemovedFiles($removedFiles);
    }

    /**
     * {@inheritdoc}
     */
    public function updateCode(PackageInterface $initial, PackageInterface $target)
    {
        $actualRootDir       = $this->rubedoRootDir;
        $this->rubedoRootDir = $this->generateTempDir();
        $fileSystem          = $this->fileSystem;

        if ($this->io->isVerbose()) {
            $this->io->write("Installing in temporary directory.");
        }
        $this->installCode($target);

        $removedFiles = $this->getRemovedFiles($target, $initial);
        $this->cleanTempDir();

        if ($this->io->isVerbose()) {
            $this->io->write("Updating new code over existing installation.");
        }
        $fileSystem->copyThenRemove($this->rubedoRootDir, $actualRootDir);

        $this->rubedoRootDir = $actualRootDir;

        $this->deleteRemovedFiles($removedFiles);
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

    /**
     * Retrieve removed files list between two package versions
     *
     * @param PackageInterface $targert new package version to install
     * @param PackageInterface $initial previous package version
     *
     * @return array
     */
    protected function getRemovedFiles(PackageInterface $target, PackageInterface $initial)
    {
        $output       = false;
        $removedFiles = array();
        if ($initial->getSourceReference() != $target->getSourceReference()) {
            if ($this->io->isVerbose()) {
                $this->io->write("Retrieving list of removed files from previous version installed.");
            }
            $command = sprintf(
                'git diff --summary --diff-filter=D %s %s -- | cut -d" " -f5',
                $initial->getSourceReference(),
                $target->getSourceReference()
           );

            if (0 !== $this->process->execute($command, $output, $this->rubedoRootDir)) {
                throw new \RuntimeException(
                    'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput()
               );
            }
            $removedFiles = explode(PHP_EOL, rtrim($output));
        }

        return $removedFiles;
    }

    /**
     * Remove directories / files that should not be copied
     */
    protected function cleanTempDir()
    {
        // Remove directories / files that should not be copied
        $this->fileSystem->unlink($this->rubedoRootDir . '/.gitignore');
        $this->fileSystem->removeDirectoryPhp($this->rubedoRootDir . '/.git');
        $this->fileSystem->removeDirectoryPhp($this->rubedoRootDir . '/extensions');
    }

    /**
     * Delete, from Rubedo root dir, all files that have been remove since previous package version
     *
     * @param array $removedFiles files list to be removed
     */
    protected function deleteRemovedFiles($removedFiles)
    {
        foreach ($removedFiles as $file) {
            $filePath = $this->rubedoRootDir . '/' . $file;
            if ($file != '' && file_exists($filePath)) {
                if ($this->io->isVerbose()) {
                    $this->io->write(sprintf('removing file %s ', $filePath));
                }
                $this->fileSystem->unlink($filePath);
            }
        }
    }
}
