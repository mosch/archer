<?php
namespace Icecave\Archer\Travis;

use Icecave\Archer\Configuration\ComposerConfigurationReader;
use Icecave\Archer\Configuration\ConfigurationFileFinder;
use Icecave\Archer\FileSystem\FileSystem;
use PHPUnit_Framework_TestCase;
use Phake;
use stdClass;

class TravisConfigManagerTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phake::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->fileFinder = Phake::mock('Icecave\Archer\Configuration\ConfigurationFileFinder');
        $this->composerConfigReader = Phake::mock('Icecave\Archer\Configuration\ComposerConfigurationReader');
        $this->isolator = Phake::mock('Icecave\Archer\Support\Isolator');
        $this->manager = new TravisConfigManager(
            $this->fileSystem,
            $this->fileFinder,
            $this->composerConfigReader,
            $this->isolator
        );

        Phake::when($this->fileFinder)->find(Phake::anyParameters())->thenReturn('/real/path/to/template');

        $this->composerConfig = new stdClass;
        $this->composerConfig->require = new stdClass;
        $this->composerConfig->require->php = '>=5.3';

        Phake::when($this->composerConfigReader)->read(Phake::anyParameters())->thenReturn($this->composerConfig);
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->manager->fileSystem());
        $this->assertSame($this->fileFinder, $this->manager->fileFinder());
        $this->assertSame($this->composerConfigReader, $this->manager->composerConfigReader());
    }

    public function testConstructorDefaults()
    {
        $this->manager = new TravisConfigManager;

        $this->assertEquals(new FileSystem, $this->manager->fileSystem());
        $this->assertEquals(new ConfigurationFileFinder, $this->manager->fileFinder());
        $this->assertEquals(new ComposerConfigurationReader, $this->manager->composerConfigReader());
    }

    public function testPublicKeyCache()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.key')->thenReturn(false);

        $this->assertNull($this->manager->publicKeyCache('/path/to/project'));

        Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.key');
    }

    public function testPublicKeyCacheExists()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.key')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.key')->thenReturn('<key data>');

        $this->assertSame('<key data>', $this->manager->publicKeyCache('/path/to/project'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.key'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.key')
        );
    }

    public function testSetPublicKeyCache()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.key')->thenReturn(false);

        $this->assertTrue($this->manager->setPublicKeyCache('/path/to/project', '<key data>'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.key'),
            Phake::verify($this->fileSystem)->write('/path/to/project/.travis.key', '<key data>')
        );
    }

    public function testSetPublicKeyCacheSame()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.key')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.key')->thenReturn('<key data>');

        $this->assertFalse($this->manager->setPublicKeyCache('/path/to/project', '<key data>'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.key'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.key')
        );

        Phake::verify($this->fileSystem, Phake::never())->write('/path/to/project/.travis.key', '<key data>');
    }

    public function testSetPublicKeyCacheDelete()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.key')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.key')->thenReturn('<key data>');

        $this->assertTrue($this->manager->setPublicKeyCache('/path/to/project', null));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.key'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.key'),
            Phake::verify($this->fileSystem)->delete('/path/to/project/.travis.key')
        );
    }

    public function testSecureEnvironmentCache()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(false);

        $this->assertNull($this->manager->secureEnvironmentCache('/path/to/project'));
        Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.env');
    }

    public function testSecureEnvironmentCacheExists()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.env')->thenReturn('<env data>');

        $this->assertSame('<env data>', $this->manager->secureEnvironmentCache('/path/to/project'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.env'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.env')
        );
    }

    public function testSetSecureEnvironmentCache()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(false);

        $this->assertTrue($this->manager->setSecureEnvironmentCache('/path/to/project', '<env data>'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.env'),
            Phake::verify($this->fileSystem)->write('/path/to/project/.travis.env', '<env data>')
        );
    }

    public function testSetSecureEnvironmentCacheSame()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.env')->thenReturn('<env data>');

        $this->assertFalse($this->manager->setSecureEnvironmentCache('/path/to/project', '<env data>'));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.env'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.env')
        );
        Phake::verify($this->fileSystem, Phake::never())->write('/path/to/project/.travis.env', '<env data>');
    }

    public function testSetSecureEnvironmentCacheDelete()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.env')->thenReturn('<env data>');

        $this->assertTrue($this->manager->setSecureEnvironmentCache('/path/to/project', null));
        Phake::inOrder(
            Phake::verify($this->fileSystem)->fileExists('/path/to/project/.travis.env'),
            Phake::verify($this->fileSystem)->read('/path/to/project/.travis.env'),
            Phake::verify($this->fileSystem)->delete('/path/to/project/.travis.env')
        );
    }

    public function testUpdateConfig()
    {
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        $templateRead = Phake::verify($this->fileSystem, Phake::times(2))->read('/real/path/to/template');
        Phake::inOrder(
            Phake::verify($this->fileSystem)
                ->copy('/path/to/archer/res/travis/travis.install.php', '/path/to/project/.travis.install'),
            Phake::verify($this->fileSystem)->chmod('/path/to/project/.travis.install', 0755),
            Phake::verify($this->fileFinder)->find(
                array('/path/to/project/test/travis-matrix.tpl.yml'),
                '/path/to/archer/res/travis/travis-matrix.tpl.yml'
            ),
            $templateRead,
            Phake::verify($this->fileFinder)
                ->find(array('/path/to/project/test/travis.tpl.yml'), '/path/to/archer/res/travis/travis.tpl.yml'),
            $templateRead,
            Phake::verify($this->fileSystem)->write(
                '/path/to/project/.travis.yml',
                '<travis: ["5.3", "5.4", "5.5", "5.6", "hhvm"], 5.5, <matrix: [{"php": "5.6"}, {"php": "hhvm"}]>, >'
            )
        );
        $this->assertFalse($result);
    }

    public function testUpdateConfigWithOAuth()
    {
        Phake::when($this->fileSystem)->fileExists('/path/to/project/.travis.env')->thenReturn(true);
        Phake::when($this->fileSystem)->read('/path/to/project/.travis.env')->thenReturn('<env data>');
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        $templateRead = Phake::verify($this->fileSystem, Phake::times(2))->read('/real/path/to/template');
        Phake::inOrder(
            Phake::verify($this->fileSystem)
                ->copy('/path/to/archer/res/travis/travis.install.php', '/path/to/project/.travis.install'),
            Phake::verify($this->fileSystem)->chmod('/path/to/project/.travis.install', 0755),
            Phake::verify($this->fileFinder)->find(
                array('/path/to/project/test/travis-matrix.tpl.yml'),
                '/path/to/archer/res/travis/travis-matrix.tpl.yml'
            ),
            $templateRead,
            Phake::verify($this->fileFinder)
                ->find(array('/path/to/project/test/travis.tpl.yml'), '/path/to/archer/res/travis/travis.tpl.yml'),
            $templateRead,
            Phake::verify($this->fileSystem)->write(
                '/path/to/project/.travis.yml',
                '<travis: ["5.3", "5.4", "5.5", "5.6", "hhvm"], 5.5, <matrix: [{"php": "5.6"}, {"php": "hhvm"}]>, - secure: "<env data>">'
            )
        );
        $this->assertTrue($result);
    }

    public function testUpdateConfigPhpVersionConstraint()
    {
        $this->composerConfig->require->php = '>=5.4';
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phake::verify($this->composerConfigReader)->read('/path/to/project');
        Phake::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.4", "5.5", "5.6", "hhvm"], 5.5, <matrix: [{"php": "5.6"}, {"php": "hhvm"}]>, >'
        );
    }

    /**
     * @group regression
     * @link https://github.com/IcecaveStudios/archer/issues/62
     */
    public function testUpdateConfigPhpVersionConstraintWithPatchVersion()
    {
        $this->composerConfig->require->php = '>=5.3.3';
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phake::verify($this->composerConfigReader)->read('/path/to/project');
        Phake::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.3", "5.4", "5.5", "5.6", "hhvm"], 5.5, <matrix: [{"php": "5.6"}, {"php": "hhvm"}]>, >'
        );
    }

    public function testUpdateConfigPhpVersionConstraintNoMatches()
    {
        $this->composerConfig->require->php = '>=6.0';
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phake::verify($this->composerConfigReader)->read('/path/to/project');
        Phake::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.5", "hhvm"], 5.5, <matrix: [{"php": "hhvm"}]>, >'
        );
    }

    public function testUpdateConfigPhpVersionNoConstraint()
    {
        unset($this->composerConfig->require->php);
        Phake::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phake::verify($this->composerConfigReader)->read('/path/to/project');
        Phake::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.3", "5.4", "5.5", "5.6", "hhvm"], 5.5, <matrix: [{"php": "5.6"}, {"php": "hhvm"}]>, >'
        );
    }

    /**
     * @dataProvider getPublishVersionData
     */
    public function testUpdateConfigPhpPublishVersion($versionConstraint, $expectedVersion)
    {
        $this->composerConfig->require->php = $versionConstraint;
        Phake::when($this->fileSystem)->read(Phake::anyParameters())->thenReturn('<template content: {php-publish-version}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phake::verify($this->composerConfigReader)->read('/path/to/project');
        Phake::verify($this->fileSystem)
            ->write('/path/to/project/.travis.yml', '<template content: ' . $expectedVersion . '>');
    }

    public function getPublishVersionData()
    {
        return array(
            array('>=5.3',  '5.5'),
            array('<=5.5',  '5.4'),
            array('>=6.0',  '5.5'),
        );
    }
}
