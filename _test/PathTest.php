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
}
