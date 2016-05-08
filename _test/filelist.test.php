<?php

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
class plugin_filelist_test extends DokuWikiTest {
    public function setUp() {
        global $conf;

        $this->pluginsEnabled[] = 'filelist';
        parent::setUp();

        // Setup config so that access to the TMP directory will be allowed
        $conf ['plugin']['filelist']['allowed_absolute_paths'] = TMP_DIR.'/filelistdata/';
        $conf ['plugin']['filelist']['web_paths'] = 'http://localhost/';
    }

    public static function setUpBeforeClass(){
        parent::setUpBeforeClass();

        // copy test files to test directory
        TestUtils::rcopy(TMP_DIR, dirname(__FILE__) . '/filelistdata');
    }
    
    /**
     * This function checks that all files are listed in not recursive mode.
     */
    public function test_not_recursive () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=list&direct=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        // We should find:
        // - example.txt
        // - exampleimage.png
        $result = strpos ($xhtml, 'example.txt');
        $this->assertFalse($result===false, '"example.txt" not listed');
        $result = strpos ($xhtml, 'exampleimage.png');
        $this->assertFalse($result===false, '"exampleimage.png" not listed');
    }

    /**
     * This function checks that all files are listed in recursive mode.
     */
    public function test_recursive () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=list&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        // We should find:
        // - exampledir
        //   - example2.txt
        // - example.txt
        // - exampleimage.png
        $result = strpos ($xhtml, 'exampledir');
        $this->assertFalse($result===false, '"exampledir" not listed');
        $result = strpos ($xhtml, 'example2.txt');
        $this->assertFalse($result===false, '"example2.txt" not listed');
        $result = strpos ($xhtml, 'example.txt');
        $this->assertFalse($result===false, '"example.txt" not listed');
        $result = strpos ($xhtml, 'exampleimage.png');
        $this->assertFalse($result===false, '"exampleimage.png" not listed');
    }

    /**
     * This function checks that the unordered list mode
     * generates the expected XHTML structure.
     */
    public function test_list () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=list&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new DOMDocument();
        $doc->loadHTML($xhtml);

        $first = $doc->documentElement;
        $this->assertEquals('html', $first->tagName);

        $children = $first->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('body', $children[0]->nodeName);

        // We should have 'div' first
        $children = $children[0]->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('div', $children[0]->nodeName);

        // It should have the childs text, 'ol'
        $children = $children[0]->childNodes;
        $this->assertTrue($children->length==2);
        $this->assertEquals('#text', $children[0]->nodeName);
        $this->assertEquals('ul', $children[1]->nodeName);

        // The 'ol' element should have 3 'li' childs
        $children = $children[1]->childNodes;
        $this->assertTrue($children->length==6);
        $this->assertEquals('li', $children[0]->nodeName);
        $this->assertEquals('#text', $children[1]->nodeName);
        $this->assertEquals('li', $children[2]->nodeName);
        $this->assertEquals('#text', $children[3]->nodeName);
        $this->assertEquals('li', $children[4]->nodeName);
        $this->assertEquals('#text', $children[5]->nodeName);

        // First child of first 'li' should be the link to 'example.txt'
        $node = $children[0];
        $node_childs = $node->childNodes;
        $this->assertEquals('level1', $node->getAttribute('class'));
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example.txt', $node_childs[0]->nodeValue);

        // First child of second 'li' is directory 'exampledir' and another 'ol'
        $node = $children[2];
        $node_childs = $node->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('#text', $node_childs[0]->nodeName);
        $this->assertEquals('exampledir', $node_childs[0]->nodeValue);
        $this->assertEquals('ul', $node_childs[1]->nodeName);

        // That 'ol' should have one 'li' with 'class=level2'
        $node_childs = $node_childs[1]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('li', $node_childs[0]->nodeName);
        $this->assertEquals('level2', $node_childs[0]->getAttribute('class'));
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // The link of that 'li' should reference 'example2.txt'
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example2.txt', $node_childs[0]->nodeValue);

        // First child of third 'li' should be the link to 'exampleimage.png'
        $node = $children[4];
        $node_childs = $node->childNodes;
        $this->assertEquals('level1', $node->getAttribute('class'));
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('exampleimage.png', $node_childs[0]->nodeValue);
    }

    /**
     * This function checks that the ordered list mode
     * generates the expected XHTML structure.
     */
    public function test_olist () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=olist&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new DOMDocument();
        $doc->loadHTML($xhtml);

        $first = $doc->documentElement;
        $this->assertEquals('html', $first->tagName);

        $children = $first->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('body', $children[0]->nodeName);

        // We should have 'div' first
        $children = $children[0]->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('div', $children[0]->nodeName);

        // It should have the childs text, 'ol'
        $children = $children[0]->childNodes;
        $this->assertTrue($children->length==2);
        $this->assertEquals('#text', $children[0]->nodeName);
        $this->assertEquals('ol', $children[1]->nodeName);

        // The 'ol' element should have 3 'li' childs
        $children = $children[1]->childNodes;
        $this->assertTrue($children->length==6);
        $this->assertEquals('li', $children[0]->nodeName);
        $this->assertEquals('#text', $children[1]->nodeName);
        $this->assertEquals('li', $children[2]->nodeName);
        $this->assertEquals('#text', $children[3]->nodeName);
        $this->assertEquals('li', $children[4]->nodeName);
        $this->assertEquals('#text', $children[5]->nodeName);

        // First child of first 'li' should be the link to 'example.txt'
        $node = $children[0];
        $node_childs = $node->childNodes;
        $this->assertEquals('level1', $node->getAttribute('class'));
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example.txt', $node_childs[0]->nodeValue);

        // First child of second 'li' is directory 'exampledir' and another 'ol'
        $node = $children[2];
        $node_childs = $node->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('#text', $node_childs[0]->nodeName);
        $this->assertEquals('exampledir', $node_childs[0]->nodeValue);
        $this->assertEquals('ol', $node_childs[1]->nodeName);

        // That 'ol' should have one 'li' with 'class=level2'
        $node_childs = $node_childs[1]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('li', $node_childs[0]->nodeName);
        $this->assertEquals('level2', $node_childs[0]->getAttribute('class'));
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // The link of that 'li' should reference 'example2.txt'
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example2.txt', $node_childs[0]->nodeValue);

        // First child of third 'li' should be the link to 'exampleimage.png'
        $node = $children[4];
        $node_childs = $node->childNodes;
        $this->assertEquals('level1', $node->getAttribute('class'));
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('exampleimage.png', $node_childs[0]->nodeValue);
    }

    /**
     * This function checks that the page mode
     * generates the expected XHTML structure.
     */
    public function test_page () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=page&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new DOMDocument();
        $doc->loadHTML($xhtml);

        $first = $doc->documentElement;
        $this->assertEquals('html', $first->tagName);

        $children = $first->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('body', $children[0]->nodeName);

        // We should have a 'ul', 'h1', '#test' and 'div' node
        $children = $children[0]->childNodes; 
        $this->assertTrue($children->length==4);
        $this->assertEquals('ul', $children[0]->nodeName);
        $this->assertEquals('h1', $children[1]->nodeName);
        $this->assertEquals('#text', $children[2]->nodeName);
        $this->assertEquals('div', $children[3]->nodeName);

        // 'ul' should have the childs 'li', text, 'li', text
        //$children = $children[0]->childNodes;
        $node_childs = $children[0]->childNodes;
        $this->assertTrue($children->length==4);
        $this->assertEquals('li', $node_childs[0]->nodeName);
        $this->assertEquals('#text', $node_childs[1]->nodeName);
        $this->assertEquals('li', $node_childs[2]->nodeName);
        $this->assertEquals('#text', $node_childs[3]->nodeName);

        // Check first 'li' contents
        $node = $node_childs[0];
        $node_childs = $node->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example.txt', $node_childs[0]->nodeValue);

        // Check second 'li' contents
        $node = $children[0]->childNodes;
        $node_childs = $node[2]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('exampleimage.png', $node_childs[0]->nodeValue);

        // Check 'h1' contents
        $node = $children[1];
        $this->assertEquals('h1', $node->nodeName);
        $this->assertEquals('exampledir', $node->nodeValue);

        // Check 'div' contents
        $node = $children[3];
        $this->assertEquals('div', $node->nodeName);
        $this->assertEquals('level1', $node->getAttribute('class'));

        // Check 'div' childs
        $node_childs = $node->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('#text', $node_childs[0]->nodeName);
        $this->assertEquals('ul', $node_childs[1]->nodeName);

        // Check 'ul' childs, we should have 'li'
        $node_childs = $node_childs[1]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('li', $node_childs[0]->nodeName);
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // The 'li' should have a 'a'
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example2.txt', $node_childs[0]->nodeValue);
    }

    /**
     * This function checks that the table mode
     * generates the expected XHTML structure.
     */
    public function test_table () {
        global $conf;

        // Render filelist
        $instructions = p_get_instructions('{{filelist>'.TMP_DIR.'/filelistdata/*&style=table&direct=1&recursive=1}}');
        $xhtml = p_render('xhtml', $instructions, $info);

        $doc = new DOMDocument();
        $doc->loadHTML($xhtml);

        $first = $doc->documentElement;
        $this->assertEquals('html', $first->tagName);

        $children = $first->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('body', $children[0]->nodeName);

        // We should have a 'div' node
        $children = $children[0]->childNodes; 
        $this->assertTrue($children->length==1);
        $this->assertEquals('div', $children[0]->nodeName);
        $this->assertEquals('filelist-plugin', $children[0]->getAttribute('class'));

        // Check 'div' childs
        $children = $children[0]->childNodes;
        $this->assertTrue($children->length==3);
        $this->assertEquals('#text', $children[0]->nodeName);
        $this->assertEquals('div', $children[1]->nodeName);
        $this->assertEquals('table sectionedit1', $children[1]->getAttribute('class'));
        $this->assertEquals('#text', $children[2]->nodeName);

        // Check inner 'div' content
        $children = $children[1]->childNodes;
        $this->assertTrue($children->length==1);
        $this->assertEquals('table', $children[0]->nodeName);

        // Check inner 'table' content
        $children = $children[0]->childNodes;
        $this->assertTrue($children->length==3);
        $this->assertEquals('tr', $children[0]->nodeName);
        $this->assertEquals('tr', $children[1]->nodeName);
        $this->assertEquals('tr', $children[2]->nodeName);

        // Check table row 1
        $node_childs = $children[0]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('td', $node_childs[0]->nodeName);
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // Check table row 1/cell 1 content
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('example.txt', $node_childs[0]->nodeValue);

        // Check table row 2
        $node_childs = $children[1]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('td', $node_childs[0]->nodeName);
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // Check table row 2/cell 1 content
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('exampledir/example2.txt', $node_childs[0]->nodeValue);

        // Check table row 3
        $node_childs = $children[2]->childNodes;
        $this->assertTrue($node_childs->length==2);
        $this->assertEquals('td', $node_childs[0]->nodeName);
        $this->assertEquals('#text', $node_childs[1]->nodeName);

        // Check table row 3/cell 1 content
        $node_childs = $node_childs[0]->childNodes;
        $this->assertTrue($node_childs->length==1);
        $this->assertEquals('a', $node_childs[0]->nodeName);
        $this->assertEquals('exampleimage.png', $node_childs[0]->nodeValue);

        /*print_r ($node_childs->children[1]);
        foreach ($node_childs as $node) {
            print ("\nTEST:".$node->nodeName." : ".$node->nodeValue."\n");
        }*/
    }
}
