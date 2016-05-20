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
     * List of files to ignore during package installation / update
     * @var array
     */
    protected $filesToIgnore = array(
        '.gitignore'
    );

    /**
     * List of directories to ignore during package installation / update
     * @var array
     */
    protected $dirsToIgnore = array(
        '.git',
        'extensions'
    );

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
        if (isset($options['rubedo-files-to-ignore'])) {
           $this->filesToIgnore = $options['rubedo-files-to-ignore'];
        }
        if (isset($options['rubedo-dirs-to-ignore'])) {
            $this->dirsToIgnore = $options['rubedo-dirs-to-ignore'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
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
        if (!is_dir($installPath) || $this->fileSystem->isDirEmpty($installPath)) {
            return parent::install($repo, $package);
        }

        $actualRootDir       = $this->rubedoRootDir;
        $this->rubedoRootDir = $this->generateTempDir();
        if ($this->io->isVerbose()) {
            $this->io->write("Installing in temporary directory.");
        }
        parent::install($repo, $package);

        // Retrieving previous package version
        $oldPkg = false;
        foreach ($repo->getPackages() as $installedPkg) {
            if ($installedPkg->getName() == $package->getName()) {
                $oldPkg = $installedPkg;
            }
        }

        // Retrieving list of package files removed from previous installed version
        $removedFiles = $oldPkg ? $this->getRemovedFiles($package, $oldPkg) : array();

        $this->installRubedoCoreSources($actualRootDir, $removedFiles);
    }

    /**
     * {@inheritdoc}
     */
    public function updateCode(PackageInterface $initial, PackageInterface $target)
    {
        $actualRootDir       = $this->rubedoRootDir;
        $this->rubedoRootDir = $this->generateTempDir();

        $this->installCode($target);

        // Retrieving list of package files removed from previous installed version
        $removedFiles = $this->getRemovedFiles($target, $initial);

        $this->installRubedoCoreSources($actualRootDir, $removedFiles);
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
                'git diff --name-only --diff-filter=D %s %s',
                $initial->getSourceReference(),
                $target->getSourceReference()
           );

            if (0 !== $this->process->execute($command, $output, $this->rubedoRootDir)) {
                throw new \RuntimeException(
                    'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput()
               );
            }
            $removedFiles = explode(PHP_EOL, ltrim(rtrim($output)));
        }

        return $removedFiles;
    }

    /**
     * Remove directories / files that should not be copied
     */
    protected function cleanTempDir()
    {
        foreach ($this->filesToIgnore as $fileToIgnore) {
            $this->fileSystem->unlink($this->rubedoRootDir . '/' . $fileToIgnore);
        }

        foreach ($this->dirsToIgnore as $dirToIgnore) {
            $this->fileSystem->removeDirectoryPhp($this->rubedoRootDir . '/' . $dirToIgnore);
        }
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
            if ($file != '' && file_exists($filePath) && is_file($filePath)) {
                if ($this->io->isVerbose()) {
                    $this->io->write(sprintf('removing file %s ', $filePath));
                }
                $this->fileSystem->unlink($filePath);
            }
        }
    }

    /**
     * Install Rubedo Core sources
     *
     * @param string $actualRootDir Rubedo root dir for project
     * @param array  $removedFiles  List of old files to remove after installation
     */
    protected function installRubedoCoreSources($actualRootDir, $removedFiles)
    {
        if ($this->io->isVerbose()) {
            $this->io->write("Updating new code over existing installation.");
        }
        // Removing files / directories to ignore from installation from temporary dir
        $this->cleanTempDir();
        $this->fileSystem->copyThenRemove($this->rubedoRootDir, $actualRootDir);

        $this->rubedoRootDir = $actualRootDir;

        // Deleting files removed from the previous package release
        $this->deleteRemovedFiles($removedFiles);
    }
}
