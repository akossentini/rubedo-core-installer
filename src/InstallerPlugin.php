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
use Composer\Plugin\PluginInterface;

/**
 * Rubedo Core Composer Installer Plugin activation class
 *
 * @category  Rubedo
 * @package   Rubedo.ComposerInstallerPlugin
 * @author    Guillaume Maïssa <g.maissa@novactive.com>
 * @copyright 2016 Novactive
 */
class InstallerPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->addInstaller(new CoreInstaller($io, $composer));
    }
}
