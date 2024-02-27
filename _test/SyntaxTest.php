<?php

namespace dokuwiki\plugin\filelist\test;

use DokuWikiTest;
use DOMWrap\Document;


/**
 * Tests for the filelist plugin.
 *
 * These test assume that the directory filelist has the following content:
 * - exampledir (directory)
 *   - example2.txt (text file)
 * - example.txt (text file)
 * - exampleimage.png (image file)
 *
 * @group plugin_filelist
 * @group plugins
 */
class plugin_filelist_test extends DokuWikiTest
{

    public function setUp(): void
    {
        global $conf;

        $this->pluginsEnabled[] = 'filelist';
        parent::setUp();

        // Setup config so that access to the TMP directory will be allowed
        $conf ['plugin']['filelist']['paths'] = TMP_DIR . '/filelistdata/' . "\n" . 'W> http://localhost/';

    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // copy test files to test directory
        \TestUtils::rcopy(TMP_DIR, dirname(__FILE__) . '/filelistdata');
    }

    /**
     * Run a list of checks on the given document
     *
     * @param Document $doc
     * @param array $structure Array of selectors and expected count or content
     * @return void
     */
    protected function structureCheck(Document $doc, $structure)
    {
        foreach ($structure as $selector => $expected) {
            if (is_numeric($expected)) {
                $this->assertEquals(
                    $expected,
                    $doc->find($selector)->count(),
                    'Selector ' . $selector . ' not found'
                );
            } else {
                $this->assertStringContainsString(
                    $expected,
                    $doc->find($selector)->text(),
                    'Selector ' . $selector . ' not found'
                );
            };
        }
    }


    /**
     * This function checks that all files are listed in not recursive mode.
     */
    public function test_not_recursive()
    {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>' . TMP_DIR . '/filelistdata/*&style=list&direct=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        // We should find:
        // - example.txt
        // - exampleimage.png
        $result = strpos($xhtml, 'example.txt');
        $this->assertFalse($result === false, '"example.txt" not listed');
        $result = strpos($xhtml, 'exampleimage.png');
        $this->assertFalse($result === false, '"exampleimage.png" not listed');
    }

    /**
     * This function checks that all files are listed in recursive mode.
     */
    public function test_recursive()
    {
        // Render filelist
        $instructions = p_get_instructions('{{filelist>' . TMP_DIR . '/filelistdata/*&style=list&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        // We should find:
        // - exampledir
        //   - example2.txt
        // - example.txt
        // - exampleimage.png
        $result = strpos($xhtml, 'exampledir');
        $this->assertFalse($result === false, '"exampledir" not listed');
        $result = strpos($xhtml, 'example2.txt');
        $this->assertFalse($result === false, '"example2.txt" not listed');
        $result = strpos($xhtml, 'example.txt');
        $this->assertFalse($result === false, '"example.txt" not listed');
        $result = strpos($xhtml, 'exampleimage.png');
        $this->assertFalse($result === false, '"exampleimage.png" not listed');
    }

    /**
     * This function checks that the unordered list mode
     * generates the expected XHTML structure.
     */
    public function testUnorderedList()
    {
        // Render filelist
        $instructions = p_get_instructions('{{filelist>' . TMP_DIR . '/filelistdata/*&style=list&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new Document();
        $doc->html($xhtml);

        $structure = [
            'div.filelist-plugin' => 1,
            'div.filelist-plugin > ul' => 1,
            'div.filelist-plugin > ul > li' => 3,
            'div.filelist-plugin > ul > li:nth-child(1)' => 1,
            'div.filelist-plugin > ul > li:nth-child(1) a' => 'example.txt',
            'div.filelist-plugin > ul > li:nth-child(2) ul' => 1,
            'div.filelist-plugin > ul > li:nth-child(2) ul > li' => 1,
            'div.filelist-plugin > ul > li:nth-child(2) ul > li a' => 'example2.txt',
        ];

        $this->structureCheck($doc, $structure);
    }

    /**
     * This function checks that the ordered list mode
     * generates the expected XHTML structure.
     */
    public function testOrderedList()
    {
        // Render filelist
        $instructions = p_get_instructions('{{filelist>' . TMP_DIR . '/filelistdata/*&style=olist&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new Document();
        $doc->html($xhtml);

        $structure = [
            'div.filelist-plugin' => 1,
            'div.filelist-plugin > ol' => 1,
            'div.filelist-plugin > ol > li' => 3,
            'div.filelist-plugin > ol > li:nth-child(1)' => 1,
            'div.filelist-plugin > ol > li:nth-child(1) a' => 'example.txt',
            'div.filelist-plugin > ol > li:nth-child(2) ol' => 1,
            'div.filelist-plugin > ol > li:nth-child(2) ol > li' => 1,
            'div.filelist-plugin > ol > li:nth-child(2) ol > li a' => 'example2.txt',
        ];

        $this->structureCheck($doc, $structure);
    }

    /**
     * This function checks that the table mode
     * generates the expected XHTML structure.
     */
    public function test_table()
    {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>' . TMP_DIR . '/filelistdata/*&style=table&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new Document();
        $doc->html($xhtml);

        $structure = [
            'div.filelist-plugin' => 1,
            'div.filelist-plugin table' => 1,
            'div.filelist-plugin table > tbody > tr' => 3,
            'div.filelist-plugin table > tbody > tr:nth-child(1) a' => 'example.txt',
            'div.filelist-plugin table > tbody > tr:nth-child(2) a' => 'exampledir/example2.txt',
            'div.filelist-plugin table > tbody > tr:nth-child(3) a' => 'exampleimage.png',
        ];

        $this->structureCheck($doc, $structure);
    }
}
