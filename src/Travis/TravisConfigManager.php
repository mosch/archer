<?php
namespace Icecave\Archer\Travis;

use Icecave\Archer\Configuration\ComposerConfigurationReader;
use Icecave\Archer\Configuration\ConfigurationFileFinder;
use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\Support\Composer\Package\LinkConstraint\VersionConstraint;
use Icecave\Archer\Support\Composer\Package\Version\VersionParser;
use Icecave\Archer\Support\Isolator;

class TravisConfigManager
{
    /**
     * Stable PHP versions supported by Travis.
     */
    public static $STABLE_VERSIONS = array('5.3', '5.4', '5.5');

    /**
     * Unstable PHP versions supported by Travis.
     */
    public static $UNSTABLE_VERSIONS = array('5.6');

    /**
     * Alternate PHP versions supported by Travis.
     */
    public static $ALTERNATE_VERSIONS = array('hhvm');

    /**
     * @param FileSystem|null                  $fileSystem
     * @param ConfigurationFileFinder|null     $fileFinder
     * @param ComposerConfigurationReader|null $composerConfigReader
     * @param Isolator|null                    $isolator
     */
    public function __construct(
        FileSystem $fileSystem = null,
        ConfigurationFileFinder $fileFinder = null,
        ComposerConfigurationReader $composerConfigReader = null,
        Isolator $isolator = null
    ) {
        if (null === $fileSystem) {
            $fileSystem = new FileSystem;
        }
        if (null === $fileFinder) {
            $fileFinder = new ConfigurationFileFinder;
        }
        if (null === $composerConfigReader) {
            $composerConfigReader = new ComposerConfigurationReader;
        }

        $this->fileSystem = $fileSystem;
        $this->fileFinder = $fileFinder;
        $this->composerConfigReader = $composerConfigReader;
        $this->versionParser = new VersionParser;
        $this->isolator = Isolator::get($isolator);
    }

    /**
     * @return FileSystem
     */
    public function fileSystem()
    {
        return $this->fileSystem;
    }

    /**
     * @return ConfigurationFileFinder
     */
    public function fileFinder()
    {
        return $this->fileFinder;
    }

    /**
     * @return ComposerConfigurationReader
     */
    public function composerConfigReader()
    {
        return $this->composerConfigReader;
    }

    /**
     * @param string $packageRoot
     *
     * @return string|null
     */
    public function publicKeyCache($packageRoot)
    {
        $publicKeyPath = sprintf('%s/.travis.key', $packageRoot);
        if ($this->fileSystem()->fileExists($publicKeyPath)) {
            return $this->fileSystem()->read($publicKeyPath);
        }

        return null;
    }

    /**
     * @param string      $packageRoot
     * @param string|null $publicKey
     *
     * @return boolean
     */
    public function setPublicKeyCache($packageRoot, $publicKey)
    {
        $publicKeyPath = sprintf('%s/.travis.key', $packageRoot);

        // Key is the same as existing one, do nothing ...
        if ($this->publicKeyCache($packageRoot) === $publicKey) {
            return false;

        // Key is null, remove file ...
        } elseif (null === $publicKey) {
            $this->fileSystem()->delete($publicKeyPath);

        // Key is provided, write file ...
        } else {
            $this->fileSystem()->write($publicKeyPath, $publicKey);
        }

        return true;
    }

    /**
     * @param string $packageRoot
     *
     * @return string|null
     */
    public function secureEnvironmentCache($packageRoot)
    {
        $envPath = sprintf('%s/.travis.env', $packageRoot);
        if ($this->fileSystem()->fileExists($envPath)) {
            return $this->fileSystem()->read($envPath);
        }

        return null;
    }

    /**
     * @param string      $packageRoot
     * @param string|null $secureEnvironment
     *
     * @return boolean
     */
    public function setSecureEnvironmentCache($packageRoot, $secureEnvironment)
    {
        $envPath = sprintf('%s/.travis.env', $packageRoot);

        // Environment is the same as existing one, do nothing ...
        if ($this->secureEnvironmentCache($packageRoot) === $secureEnvironment) {
            return false;

        // Environment is null, remove file ...
        } elseif (null === $secureEnvironment) {
            $this->fileSystem()->delete($envPath);

        // Environment is provided, write file ...
        } else {
            $this->fileSystem()->write($envPath, $secureEnvironment);
        }

        return true;
    }

    /**
     * @param string $archerPackageRoot
     * @param string $packageRoot
     *
     * @return boolean
     */
    public function updateConfig($archerPackageRoot, $packageRoot)
    {
        $source = sprintf('%s/res/travis/travis.install.php', $archerPackageRoot);
        $target = sprintf('%s/.travis.install', $packageRoot);
        $this->fileSystem()->copy($source, $target);
        $this->fileSystem()->chmod($target, 0755);

        $secureEnvironment = $this->secureEnvironmentCache($packageRoot);

        if ($secureEnvironment) {
            $tokenEnvironment = sprintf('- secure: "%s"', $secureEnvironment);
        } else {
            $tokenEnvironment = '';
        }

        list($phpVersions, $phpPublishVersion) = $this->phpVersions($packageRoot);

        $allowFailureVersions = $this->allowFailureVersions($phpVersions);
        if (count($allowFailureVersions) > 0) {
            $allowFailureVersions = '[{"php": "' . implode('"}, {"php": "', $allowFailureVersions) . '"}]';

            $template = $this->fileSystem()->read(
                $this->findTemplatePath($archerPackageRoot, $packageRoot, $secureEnvironment !== null, 'travis-matrix')
            );
            $matrix = str_replace(array('{allow-failure-versions}'), array($allowFailureVersions), $template);
        } else {
            $matrix = '';
        }

        $phpVersions = '["' . implode('", "', $phpVersions) . '"]';

        // Re-build travis.yml.
        $template = $this->fileSystem()->read(
            $this->findTemplatePath($archerPackageRoot, $packageRoot, $secureEnvironment !== null)
        );

        $this->fileSystem()->write(
            sprintf('%s/.travis.yml', $packageRoot),
            str_replace(
                array('{token-env}', '{php-versions}', '{matrix}', '{php-publish-version}'),
                array($tokenEnvironment, $phpVersions, $matrix, $phpPublishVersion),
                $template
            )
        );

        // Return true if artifact publication is enabled.
        return $secureEnvironment !== null;
    }

    /**
     * @param string  $archerPackageRoot
     * @param string  $packageRoot
     * @param boolean $hasEncryptedEnvironment
     *
     * @return string
     */
    protected function findTemplatePath($archerPackageRoot, $packageRoot, $hasSecureEnvironment, $templateName = null)
    {
        return $this->fileFinder()->find(
            $this->candidateTemplatePaths($packageRoot, $hasSecureEnvironment, $templateName),
            $this->defaultTemplatePath($archerPackageRoot, $hasSecureEnvironment, $templateName)
        );
    }

    /**
     * @param string  $packageRoot
     * @param boolean $hasSecureEnvironment
     *
     * @return array<string>
     */
    protected function candidateTemplatePaths($packageRoot, $hasSecureEnvironment, $templateName = null)
    {
        return array(
            sprintf(
                '%s/test/%s',
                $packageRoot,
                $this->templateFilename($hasSecureEnvironment, $templateName)
            )
        );
    }

    /**
     * @param string  $archerPackageRoot
     * @param boolean $hasSecureEnvironment
     *
     * @return string
     */
    protected function defaultTemplatePath($archerPackageRoot, $hasSecureEnvironment, $templateName = null)
    {
        return sprintf(
            '%s/res/travis/%s',
            $archerPackageRoot,
            $this->templateFilename($hasSecureEnvironment, $templateName)
        );
    }

    protected function templateFilename($hasSecureEnvironment, $templateName = null)
    {
        if (null === $templateName) {
            $templateName = 'travis';
        }

        return $templateName . '.tpl.yml';
    }

    protected function phpVersions($packageRoot)
    {
        list($phpVersions, $phpPublishVersion) = $this->numericPhpVersions($packageRoot);

        return array(array_merge($phpVersions, static::$ALTERNATE_VERSIONS), $phpPublishVersion);
    }

    protected function numericPhpVersions($packageRoot)
    {
        $phpVersions = array_merge(static::$STABLE_VERSIONS, static::$UNSTABLE_VERSIONS);

        $config = $this->composerConfigReader->read($packageRoot);

        // If there is no constraint specified in the composer
        // configuration then use all available versions.
        if (!isset($config->require->php)) {
            return array($phpVersions, static::$STABLE_VERSIONS[count(static::$STABLE_VERSIONS) - 1]);
        }

        // Parse the constraint ...
        $constraint = $this->versionParser->parseConstraints($config->require->php);
        $filteredVersions = array();
        $phpPublishVersion = null;

        // Check each available version against the constraint ...
        foreach ($phpVersions as $version) {
            $provider = new VersionConstraint('=', $this->versionParser->normalize($version . '.' . PHP_INT_MAX));
            if ($constraint->matches($provider)) {
                $filteredVersions[] = $version;

                if (null === $phpPublishVersion || in_array($version, static::$STABLE_VERSIONS, true)) {
                    $phpPublishVersion = $version;
                }
            }
        }

        // No matches were found, use the latest stable version that travis supports ...
        if (0 === count($filteredVersions)) {
            $latestStable = static::$STABLE_VERSIONS[count(static::$STABLE_VERSIONS) - 1];

            return array(array($latestStable), $latestStable);
        }

        return array($filteredVersions, $phpPublishVersion);
    }

    protected function allowFailureVersions(array $phpVersions)
    {
        $unstableVersions = array_merge(static::$UNSTABLE_VERSIONS, static::$ALTERNATE_VERSIONS);

        $allowFailureVersions = array();
        foreach ($phpVersions as $version) {
            if (in_array($version, $unstableVersions, true)) {
                $allowFailureVersions[] = $version;
            }
        }

        return $allowFailureVersions;
    }

    private $fileSystem;
    private $fileFinder;
    private $composerConfigReader;
    private $versionParser;
    private $isolator;
}
