<?php
/**
 * Filelist Plugin: Lists files matching a given glob pattern.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/confutils.php');
require_once(DOKU_INC.'inc/pageutils.php');

define('DOKU_PLUGIN_FILELIST_NOMATCH', -1);
define('DOKU_PLUGIN_FILELIST_OUTSIDEJAIL', -2);

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_filelist extends DokuWiki_Syntax_Plugin {

    var $mediadir;

    function syntax_plugin_filelist() {
        global $conf;
        $basedir = $conf['savedir'];
        if (!$this->_path_is_absolute($basedir)) {
            $basedir = DOKU_INC . '/' . $basedir;
        }
        $this->mediadir = $this->_win_path_convert($this->_realpath($basedir.'/media').'/');
    }

    /**
     * return some info
     */
    function getInfo() {
        return array(
            'author' => 'Gina Haeussge',
            'email'  => 'gina@foosel.net',
            'date'   => '2009-11-16',
            'name'   => 'Filelist Plugin',
            'desc'   => 'Lists files matching a given glob pattern.',
            'url'    => 'http://foosel.org/snippets/dokuwiki/filelist',
        );
    }

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 222; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{filename>.+?\}\}',$mode,'plugin_filelist');
        $this->Lexer->addSpecialPattern('\{\{filelist>.+?\}\}',$mode,'plugin_filelist');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {

        // do not allow the syntax in comments
        if (!$this->getConf('allow_in_comments') && isset($_REQUEST['comment']))
        return false;

        $match = substr($match, 2, -2);
        list($type, $match) = split('>', $match, 2);
        list($pattern, $flags) = split('&', $match, 2);

        if ($type == 'filename') {
            if (strpos($flags, '|') !== FALSE) {
                list($flags, $title) = split('\|', $flags);
            } else {
                $title = '';
            }
        }

        $flags = split('&', $flags);
        $params = array(
            'sort' => 'name',
            'order' => 'asc',
            'index' => 0,
            'limit' => 0,
            'offset' => 0,
            'style' => 'list',
            'tableheader' => 0,
            'tableshowsize' => 0,
            'tableshowdate' => 0,
            'direct' => 0,
            'recursive' => 0,
            'titlefile' => '_title.txt',
        );
        foreach($flags as $flag) {
            list($name, $value) = split('=', $flag);
            $params[trim($name)] = trim($value);
        }

        // recursive filelistings are not supported for the filename command
        if ($type == 'filename') {
            $params['recursive'] = 0;
        }

        return array($type, $pattern, $params, $title);
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $conf;

        // disable caching
        $renderer->info['cache'] = false;

        list($type, $pattern, $params, $title) = $data;
        if ($mode == 'xhtml') {

            $result = $this->_create_filelist($pattern, $params);
            if ($type == 'filename') {
                $result = $this->_filter_out_directories($result);
            }

            // if we got nothing back, display a message
            if ($result == DOKU_PLUGIN_FILELIST_NOMATCH) {
                $renderer->doc .= '[n/a: ' . $this->getLang('error_nomatch') . ']';
                return true;
            } else if ($result == DOKU_PLUGIN_FILELIST_OUTSIDEJAIL) {
                $renderer->doc .= '[n/a: ' . $this->getLang('error_outsidejail') . ']';
                return true;
            }

            // if limit is set for a filelist, cut out the relevant slice from the files
            if (($type == 'filelist') && ($params['limit'] != 0)) {
                $result['files'] = array_slice($result['files'], $params['offset'], $params['limit']);
            }

            switch ($type) {

                case 'filename':

                    $filename = $result['files'][$params['index']]['name'];
                    $filepath = $result['files'][$params['index']]['path'];

                    $this->_render_link($filename, $filepath, $result['basedir'], $result['webdir'], $params, $renderer);
                    return true;

                case 'filelist':
                    if (count($result['files']) == 0)
                        break;

                    switch ($params['style']) {
                        case 'list':
                        case 'olist':
                            $renderer->doc .= '<div class="filelist-plugin">'.DOKU_LF;
                            $this->_render_list($result, $params, $renderer);
                            $renderer->doc .= '</div>'.DOKU_LF;
                            break;

                        case 'table':
                            $renderer->doc .= '<div class="filelist-plugin">'.DOKU_LF;
                            $this->_render_table($result, $params, $renderer);
                            $renderer->doc .= '</div>'.DOKU_LF;
                            break;

                        case 'page':
                            $this->_render_page($result, $params, $renderer);
                            break;
                    }
                    return true;

            }
        }
        return false;
    }

    //~~ Render functions

    /**
     * Creates the downloadlink for the given filename, based on the given
     * parameters, and adds it to the output of the renderer.
     *
     * @param $filename the name of the file
     * @param $filepath the path of the file
     * @param $basedir the basedir of the file
     * @param $webdir the base URL of the file
     * @param $params the parameters of the filelist command
     * @param $renderer the renderer to use
     * @return void
     */
    function _render_link($filename, $filepath, $basedir, $webdir, $params, &$renderer) {
        global $conf;

        //prepare for formating
        $link['target'] = $conf['target']['extern'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['class']  = 'media';
        if (!$params['direct']) {
            $link['url'] = ml(':'.str_replace('/', ':', substr($filepath, strlen($this->mediadir))));
        } else {
            $link['url'] = $webdir.substr($filepath, strlen($basedir));
        }
        $link['name']   = $filename;
        $link['title']  = $renderer->_xmlEntities($link['url']);
        if($conf['relnofollow']) $link['more'] .= ' rel="nofollow"';

        list($ext,$mime) = mimetype(basename($filepath));
        $link['class'] .= ' mediafile mf_'.$ext;

        //output formatted
        $renderer->doc .= $renderer->_formatLink($link);
    }

    /**
     * Renders a list.
     *
     * @param $result the filelist to render
     * @param $params the parameters of the filelist call
     * @param $renderer the renderer to use
     * @return void
     */
    function _render_list($result, $params, &$renderer) {
        $this->_render_list_items($result['files'], $result['basedir'], $result['webdir'], $params, $renderer);
    }

    /**
     * Recursively renders a tree of files as list items.
     *
     * @param $files the files to render
     * @param $basedir the basedir to use
     * @param $webdir the webdir to use
     * @param $params the parameters of the filelist call
     * @param $renderer the renderer to use
     * @param $level the level to render
     * @return void
     */
    function _render_list_items($files, $basedir, $webdir, $params, &$renderer, $level = 1) {
        if ($params['style'] == 'olist') {
            $renderer->listo_open();
        } else {
            $renderer->listu_open();
        }

        foreach ($files as $file) {
            if ($file['children'] !== false && $file['treesize'] > 0) {
                // render the directory and its subtree
                $renderer->listitem_open($level);
                $renderer->doc .= $file['name'];
                $this->_render_list_items($file['children'], $basedir, $webdir, $params, $renderer, $level+1);
                $renderer->listitem_close();
            } else if ($file['children'] === false) {
                // render the file
                $renderer->listitem_open($level);
                $this->_render_link($file['name'], $file['path'], $basedir, $webdir, $params, $renderer);
                $renderer->listitem_close();
            } else {
                // ignore empty directories
                continue;
            }
        }

        if ($params['style'] == 'olist') {
            $renderer->listo_close();
        } else {
            $renderer->listu_close();
        }
    }

    /**
     * Renders the files as a table, including details if configured that way.
     *
     * @param $result the filelist to render
     * @param $params the parameters of the filelist call
     * @param $renderer the renderer to use
     * @return void
     */
    function _render_table($result, $params, &$renderer) {
        global $conf;

        $renderer->table_open();

        if ($params['tableheader']) {
            $renderer->tableheader_open();
            $renderer->doc .= $this->getLang('filename');
            $renderer->tableheader_close();

            if ($params['tableshowsize']) {
                $renderer->tableheader_open();
                $renderer->doc .= $this->getLang('filesize');
                $renderer->tableheader_close();
            }

            if ($params['tableshowdate']) {
                $renderer->tableheader_open();
                $renderer->doc .= $this->getLang('lastmodified');
                $renderer->tableheader_close();
            }

        }

        foreach ($result['files'] as $file) {
            $renderer->tablerow_open();
            $renderer->tablecell_open();
            $this->_render_link($file['name'], $file['path'], $result['basedir'], $result['webdir'], $params, $renderer);
            $renderer->tablecell_close();

            if ($params['tableshowsize']) {
                $renderer->tablecell_open(1, 'right');
                $renderer->doc .= $this->_size_readable($file['size'], 'PiB', 'bi', '%01.1f %s');
                $renderer->tablecell_close();
            }

            if ($params['tableshowdate']) {
                $renderer->tablecell_open();
                $renderer->doc .= strftime($conf['dformat'], $file['mtime']);
                $renderer->tablecell_close();
            }

            $renderer->tablerow_close();
        }
        $renderer->table_close();
    }

    /**
     * Renders a page.
     *
     * @param $result the filelist to render
     * @param $params the parameters of the filelist call
     * @param $renderer the renderer to use
     * @return void
     */
    function _render_page($result, $params, &$renderer) {
        $this->_render_page_section($result['files'], $result['basedir'], $result['webdir'], $params, $renderer, $renderer->lastlevel + 1);
    }

    /**
     * Recursively renders a tree of files as page sections using headlines.
     *
     * @param $files the files to render
     * @param $basedir the basedir to use
     * @param $webdir the webdir to use
     * @param $params the parameters of the filelist call
     * @param $renderer the renderer to use
     * @param $level the level to render
     * @return void
     */
    function _render_page_section($files, $basedir, $webdir, $params, &$renderer, $level) {
        $trees = array();
        $leafs = array();

        foreach ($files as $file) {
            if ($file['children'] !== false) {
                if ($file['treesize'] > 0) {
                    $trees[] = $file;
                }
            } else {
                $leafs[] = $file;
            }
        }

        $this->_render_list_items($leafs, $basedir, $webdir, $params, $renderer);

        if ($level < 7) {
            foreach ($trees as $tree) {
                $renderer->header($tree['name'], $level, 0);
                $renderer->section_open($level);
                $this->_render_page_section($tree['children'], $basedir, $webdir, $params, $renderer, $level + 1);
                $renderer->section_close();
            }
        } else {
            $this->_render_list_items($trees, $basedir, $webdir, $params, $renderer);
        }
    }

    //~~ Filelist functions

    /**
     * Creates the filelist based on the given glob-pattern and
     * sorting and ordering parameters.
     *
     * @param $pattern the pattern
     * @param $params the parameters of the filelist command
     * @return a filelist data structure containing the found files and base-
     *         and webdir
     */
    function _create_filelist($pattern, $params) {
        global $conf;
        global $ID;

        $result = array(
            'files' => array(),
            'basedir' => false,
            'webdir' => false,
        );

        // we don't want to use $conf['media'] here as that path has symlinks resolved
        if (!$params['direct']) {
            // if media path is not absolute, precede it with the current namespace
            if ($pattern[0] != ':') {
                $pattern = ':'.getNS($ID) . ':' . $pattern;
            }
            // replace : with / and prepend mediadir
            $pattern = $this->mediadir . str_replace(':', '/', $pattern);
        } else {
            // if path is not absolute, precede it with DOKU_INC
            if (!$this->_path_is_absolute($pattern)) {
                $pattern = DOKU_INC.$pattern;
            }
        }
        // get the canonicalized basedir (without resolving symlinks)
        $dir = $this->_realpath($this->_win_path_convert(dirname($pattern))).'/';

        // if the directory does not exist, we of course have no matches
        if (!$dir || !file_exists($dir)) {
            return DOKU_PLUGIN_FILELIST_NOMATCH;
        }

        // match pattern aginst allowed paths
        $allowed_absolute_paths = split(',', $this->getConf('allowed_absolute_paths'));
        $web_paths = split(',', $this->getConf('web_paths'));
        $basedir = false;
        $webdir = false;
        if (count($allowed_absolute_paths) == count($web_paths)) {
            for($i = 0; $i < count($allowed_absolute_paths); $i++) {
                $abs_path = $this->_win_path_convert(trim($allowed_absolute_paths[$i]));
                if (strstr($dir, $abs_path) == $dir) {
                    $basedir = $abs_path;
                    $webdir = trim($web_paths[$i]);
                    break;
                }
            }
        }

        // $basedir is false if $dir was not in one of the allowed paths
        if ($basedir === false) {
            return DOKU_PLUGIN_FILELIST_OUTSIDEJAIL;
        }

        // retrieve fileinformation
        $result['basedir'] = $basedir;
        $result['webdir'] = $webdir;
        $result['files'] = $this->_crawl_files($this->_win_path_convert($pattern), $params);
        if (!$result['files']) {
            return DOKU_PLUGIN_FILELIST_NOMATCH;
        }

        // flatten filelist if the displaymode is table
        if ($params['style'] == 'table') {
            $result['files'] = $this->_flatten_filelist($result['files']);
        }

        // sort the list
        $callback = false;
        $reverseflag = false;
        if ($params['sort'] == 'mtime') {
            $callback = array($this, '_compare_mtimes');
            if ($params['order'] == 'asc') $reverseflag = true;
        } else if ($params['sort'] == 'ctime') {
            $callback = array($this, '_compare_ctimes');
            if ($params['order'] == 'asc') $reverseflag = true;
        } else if ($params['sort'] == 'size') {
            $callback = array($this, '_compare_sizes');
            if ($params['order'] == 'desc') $reverseflag = true;
        } else {
            $callback = array($this, '_compare_names');
            if ($params['order'] == 'desc') $reverseflag = true;
        }
        $this->_sort_filelist($result['files'], $callback, $reverseflag);

        // return the list
        if (count($result['files']) > 0)
            return $result;
        else
            return DOKU_PLUGIN_FILELIST_NOMATCH;
    }

    /**
     * Recursively sorts the given tree using the given callback. Optionally
     * reverses the sorted tree before returning it.
     *
     * @param $files the files to sort
     * @param $callback the callback function to use for comparison
     * @param $reverse true if the result is to be reversed
     * @return the sorted tree
     */
    function _sort_filelist(&$files, $callback, $reverse) {
        // sort subtrees
        for ($i = 0; $i < count($files); $i++) {
            if ($files[$i]['children'] !== false) {
                $children = $files[$i]['children'];
                $this->_sort_filelist($children, $callback, $reverse);
                $files[$i]['children'] = $children;
            }
        }

        // sort current tree
        usort($files, $callback);
        if ($reverse) {
            $files = array_reverse($files);
        }
    }

    /**
     * Flattens the filelist by recursively walking through all subtrees and
     * merging them with a prefix attached to the filenames.
     *
     * @param $files the tree to flatten
     * @param $prefix the prefix to attach to all processed nodes
     * @return a flattened representation of the tree
     */
    function _flatten_filelist($files, $prefix = '') {
        $result = array();
        foreach ($files as $file) {
            if ($file['children'] !== false) {
                $result = array_merge($result, $this->_flatten_filelist($file['children'], $prefix . $file['name'] . '/'));
            } else {
                $file['name'] = $prefix . $file['name'];
                $result[] = $file;
            }
        }
        return $result;
    }

    /**
     * Filters out directories and their subtrees from the result.
     *
     * @param $result the result to filter
     * @return the result without any directories contained therein,
     *         DOKU_PLUGIN_FILELIST_NOMATCH if there are no files left or
     *         the given result if it was not an array (but an errorcode)
     */
    function _filter_out_directories($result) {
        if (!is_array($result)) {
            return $result;
        }

        $filtered = array();
        $files = $result['files'];
        foreach ($files as $file) {
            if ($file['children'] === false) {
                $filtered[] = $file;
            }
        }

        if (count($filtered) == 0) {
            return DOKU_PLUGIN_FILELIST_NOMATCH;
        } else {
            $result['files'] = $filtered;
            return $result;
        }
    }

    /**
     * Does a (recursive) crawl for finging files based on a given pattern.
     * Based on a safe glob reimplementation using fnmatch and opendir.
     *
     * @param $pattern the pattern to match to
     * @param params the parameters of the filelist call
     * @return a hierarchical filelist or false if nothing could be found
     *
     * @see <http://www.php.net/manual/en/function.glob.php#71083>
     */
    function _crawl_files($pattern, $params) {
        $split = explode('/', $pattern);
        $match = array_pop($split);
        $path = implode('/', $split);
        if (!is_dir($path)) {
            return false;
        }

        if (($dir = opendir($path)) !== false) {
            $result = array();
            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    // ignore . and ..
                    continue;
                }
                if ($file == $params['titlefile']) {
                    // ignore the title file
                    continue;
                }
                if ($file[0] == '.') {
                    // ignore hidden files
                    continue;
                }
                $filepath = $path . '/' . $file;

                if ($this->_fnmatch($match, $file) || (is_dir($filepath) && $params['recursive'])) {
                    if (!$params['direct']) {
                        // exclude prohibited media files via ACLs
                        $mid = str_replace('/', ':', substr($filepath, strlen($this->mediadir)));
                        $perm = auth_quickaclcheck($mid);
                        if ($perm < AUTH_READ) continue;
                    } else {
                        if (!is_readable($filepath)) continue;
                    }

                    $filename = $file;
                    if (is_dir($filepath)) {
                        $titlefile = $filepath . '/' . $params['titlefile'];
                        if (!$params['direct']) {
                            $mid = str_replace('/', ':', substr($titlefile, strlen($this->mediadir)));
                            $perm = auth_quickaclcheck($mid);
                            if ($perm >= AUTH_READ) {
                                $filename = io_readFile($titlefile, false);
                            }
                        } else {
                            if (is_readable($titlefile)) {
                                $filename = io_readFile($titlefile, false);
                            }
                        }
                    }

                    // prepare entry
                    if (!is_dir($filepath) || $params['recursive']) {
                        $entry = array(
                            'name' => $filename,
                            'path' => $filepath,
                            'mtime' => filemtime($filepath),
                            'ctime' => filectime($filepath),
                            'size' => filesize($filepath),
                            'children' => ((is_dir($filepath) && $params['recursive']) ? $this->_crawl_files($filepath . '/' . $match, $params) : false),
                            'treesize' => 0,
                        );

                        // calculate tree size
                        if ($entry['children'] !== false) {
                            foreach ($entry['children'] as $child) {
                                $entry['treesize'] += $child['treesize'];
                            }
                        } else {
                            $entry['treesize'] = 1;
                        }

                        // add entry to result
                        $result[] = $entry;
                    }
                }
            }
            closedir($dir);
            return $result;
        } else {
            return false;
        }
    }

    //~~ Comparators

    function _compare_names($a, $b) {
        return strcmp($a['name'], $b['name']);
    }

    function _compare_ctimes($a, $b) {
        if ($a['ctime'] == $b['ctime']) {
            return 0;
        }
        return (($a['ctime'] < $b['ctime']) ? -1 : 1);
    }

    function _compare_mtimes($a, $b) {
        if ($a['mtime'] == $b['mtime']) {
            return 0;
        }
        return (($a['mtime'] < $b['mtime']) ? -1 : 1);
    }

    function _compare_sizes($a, $b) {
        if ($a['size'] == $b['size']) {
            return 0;
        }
        return (($a['size'] < $b['size']) ? -1 : 1);
    }

    //~~ Utility functions

    /**
     * Canonicalizes a given path. A bit like realpath, but without the resolving of symlinks.
     *
     * @author anonymous
     * @see <http://www.php.net/manual/en/function.realpath.php#73563>
     */
    function _realpath($path) {
        $path=explode('/', $path);
        $output=array();
        for ($i=0; $i<sizeof($path); $i++) {
            if (('' == $path[$i] && $i > 0) || '.' == $path[$i]) continue;
            if ('..' == $path[$i] && $i > 0 && '..' != $output[sizeof($output) - 1]) {
                array_pop($output);
                continue;
            }
            array_push($output, $path[$i]);
        }
        return implode('/', $output);
    }

    /**
     * Replacement for fnmatch() for windows systems.
     *
     * @author jk at ricochetsolutions dot com
     * @see <http://www.php.net/manual/en/function.fnmatch.php#71725>
     */
    function _fnmatch($pattern, $string) {
        return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'))."$#i", $string);
    }

    /**
     * Converts backslashs in paths to slashs.
     *
     * @param $path the path to convert
     * @return the converted path
     */
    function _win_path_convert($path) {
        return str_replace('\\', '/', trim($path));
    }

    /**
     * Return human readable sizes
     *
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.3.0
     * @link        http://aidanlister.com/repos/v/function.size_readable.php
     * @param       int     $size        size in bytes
     * @param       string  $max         maximum unit
     * @param       string  $system      'si' for SI, 'bi' for binary prefixes
     * @param       string  $retstring   return string format
     */
    function _size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s')
    {
        // Pick units
        $systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
        $systems['si']['size']   = 1000;
        $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
        $systems['bi']['size']   = 1024;
        $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

        // Max unit to display
        $depth = count($sys['prefix']) - 1;
        if ($max && false !== $d = array_search($max, $sys['prefix'])) {
            $depth = $d;
        }

        // Loop
        $i = 0;
        while ($size >= $sys['size'] && $i < $depth) {
            $size /= $sys['size'];
            $i++;
        }

        return sprintf($retstring, $size, $sys['prefix'][$i]);
    }

    /**
     * Determines whether a given path is absolute or relative.
     * On windows plattforms, it does so by checking whether the second character
     * of the path is a :, on all other plattforms it checks for a / as the
     * first character.
     *
     * @param $path the path to check
     * @return true if path is absolute, false otherwise
     */
    function _path_is_absolute($path) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return ($path[1] == ':');
        } else {
            return ($path[0] == '/');
        }
    }

}
