<?php

namespace dokuwiki\plugin\filelist\test;

use dokuwiki\plugin\filelist\Path;
use DokuWikiTest;

/**
 * Path related tests for the filelist plugin
 *
 * @group plugin_filelist
 * @group plugins
 */
class PathTest extends DokuWikiTest
{

    protected $path;

    public function setUp(): void
    {
        parent::setUp();

        $this->path = new Path(
            <<<EOT
C:\\xampp\\htdocs\\wiki\\
\\\\server\\share\\path\\
/linux/file/path/
/linux/another/path/../..//another/blargh/../path
  A> alias
  W> webfoo
EOT
        );
    }

    /**
     * Test the configuration parsing for paths and aliases
     */
    public function testGetPaths()
    {
        $expect = [
            'C:/xampp/htdocs/wiki/' => [
                'root' => 'C:/xampp/htdocs/wiki/',
                'web' => '/lib/plugins/filelist/file.php?root=C%3A%2Fxampp%2Fhtdocs%2Fwiki%2F&file=',
            ],
            '\\\\server/share/path/' => [
                'root' => '\\\\server/share/path/',
                'web' => '/lib/plugins/filelist/file.php?root=%5C%5Cserver%2Fshare%2Fpath%2F&file=',
            ],
            '/linux/file/path/' => [
                'root' => '/linux/file/path/',
                'web' => '/lib/plugins/filelist/file.php?root=%2Flinux%2Ffile%2Fpath%2F&file=',
            ],
            '/linux/another/path/' => [
                'root' => '/linux/another/path/',
                'alias' => 'alias/',
                'web' => 'webfoo',
            ],
            'alias/' => [
                'root' => '/linux/another/path/',
                'alias' => 'alias/',
                'web' => 'webfoo',
            ],
        ];

        $this->assertEquals($expect, $this->path->getPaths());
    }

    /**
     * Data provider for testGetPathInfoSuccess
     */
    public function providePathInfoSuccess()
    {
        return [
            ['/linux/another/path', '/linux/another/path/'],
            ['/linux/another/path/foo', '/linux/another/path/foo/'],
            ['alias', '/linux/another/path/'],
            ['alias/foo', '/linux/another/path/foo/'],
            ['C:\\xampp\\htdocs\\wiki', 'C:/xampp/htdocs/wiki/'],
            ['C:\\xampp\\htdocs\\wiki\\foo', 'C:/xampp/htdocs/wiki/foo/'],
            ['\\\\server\\share\\path\\', '\\\\server/share/path/'],
            ['\\\\server\\share\\path\\foo', '\\\\server/share/path/foo/'],
        ];
    }

    /**
     * @dataProvider providePathInfoSuccess
     */
    public function testGetPathInfoSuccess($path, $expect)
    {
        $pathInfo = $this->path->getPathInfo($path);
        $this->assertEquals($expect, $pathInfo['path']);
    }

    public function providePathInfoFailure()
    {
        return [
            ['/linux/file/path/../../../etc/'],
            ['W:\\xampp\\htdocs\\wiki\\foo\\bar'],
            ['/'],
            ['./'],
            ['../'],
        ];
    }

    /**
     * @dataProvider providePathInfoFailure
     */
    public function testGetPathInfoFailure($path)
    {
        $this->expectExceptionMessageMatches('/Path not allowed/');
        $this->path->getPathInfo($path);
    }

    /**
     * Relative paths have to be resolved to an absolute path so that file access works
     * regardless of the current working directory (which differs between doku.php and file.php)
     */
    public function testRelativePathIsResolvedToAbsolute()
    {
        $path = new Path('relative/root/');
        $pathInfo = $path->getPathInfo('relative/root/sub/file.txt', false);

        $this->assertEquals(
            Path::cleanPath(DOKU_INC, false) . '/relative/root/sub/file.txt',
            $pathInfo['path']
        );
    }

    /**
     * Absolute configured paths must be passed through unchanged
     */
    public function testAbsolutePathIsKept()
    {
        $path = new Path('/somewhere/else/');
        $pathInfo = $path->getPathInfo('/somewhere/else/file.txt', false);
        $this->assertEquals('/somewhere/else/file.txt', $pathInfo['path']);
    }

    /**
     * The wiki/data directory guard must trigger even for relatively configured roots.
     *
     * This is the regression behind issue #50: a relative root like "firmware" never matched
     * the absolute DOKU_INC, so the guard was silently bypassed and wiki files could be served.
     */
    public function testIsWikiControlled()
    {
        global $conf;

        // relative path inside the DokuWiki directory (cwd-independent)
        $this->assertTrue(Path::isWikiControlled('lib/plugins/filelist'));
        // absolute path inside the DokuWiki directory (e.g. the password hashes)
        $this->assertTrue(Path::isWikiControlled(DOKU_INC . 'conf/users.auth.php'));
        // the configured data directory
        $this->assertTrue(Path::isWikiControlled($conf['savedir'] . '/pages/wiki/dokuwiki.txt'));
        // a path completely outside the wiki must be allowed
        $this->assertFalse(Path::isWikiControlled('/some/other/place/file.txt'));
    }
}
