<?php
/**
 * Filelist Plugin: Lists files matching a given glob pattern.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

if(!defined('DOKU_INC'))
  define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
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
		
		$this->mediadir = rp(DOKU_INC.'/'.$conf['savedir'].'/media').'/';
	}

    /**
     * return some info
     */
    function getInfo() {
        return array(
            'author' => 'Gina Haeussge',
            'email'  => 'osd@foosel.net',
            'date'   => '2008-04-04',
            'name'   => 'Filelist Plugin',
            'desc'   => 'Lists files matching a given glob pattern.',
            'url'    => 'http://wiki.foosel.net/snippets/dokuwiki/filelist',
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
        );
        foreach($flags as $flag) {
            list($name, $value) = split('=', $flag);
            $params[trim($name)] = trim($value);
        }
        
        return array($type, $pattern, $params, $title);
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $conf;
        
        $renderer->info['cache'] = false;
        list($type, $pattern, $params, $title) = $data;
        $files = $this->_create_filelist($pattern, $params);
        
        if ($files == DOKU_PLUGIN_FILELIST_NOMATCH) {
        	$renderer->doc .= '[n/a: ' . $this->getLang('error_nomatch') . ']';
        	return true;
        } else if ($files == DOKU_PLUGIN_FILELIST_OUTSIDEJAIL) {
        	$renderer->doc .= '[n/a: ' . $this->getLang('error_outsidejail') . ']';
        	return true;
        }

        // if limit is set for a filelist, cut out the relevant slice from the files
        if (($type == 'filelist') && ($params['limit'] != 0)) {
            $files['names'] = array_slice($files['names'], $params['offset'], $params['limit']);
            $files['mtimes'] = array_slice($files['mtimes'], $params['offset'], $params['limit']);
            $files['ctimes'] = array_slice($files['ctimes'], $params['offset'], $params['limit']);
            $files['sizes'] = array_slice($files['sizes'], $params['offset'], $params['limit']);
        }
        
        if ($mode == 'xhtml') {
            
            switch ($type) {

				case 'filename':  
			        $filename = $files['names'][$params['index']];
			        if ($title == '') {
			            $title = basename($filename);
			        }
			        
			        $this->_create_link($filename, $files['basedir'], $files['webdir'], $params, $renderer);
		            return true;
		            
		        case 'filelist':
		        	if (count($files['names']) == 0)
		        	    break;
		        	
		        	$renderer->doc .= '<div class="filelist-plugin">'.DOKU_LF;
		        	switch ($params['style']) {
		        	    case 'list': 
		        	    	$renderer->listu_open();
				        	foreach ($files['names'] as $filename) {
				        	    $renderer->listitem_open(1);
						        $this->_create_link($filename, $files['basedir'], $files['webdir'], $params, $renderer);
				        	    $renderer->listitem_close();
				        	}
				        	$renderer->listu_close();
		        	    	break;
		        	    	
		        	    case 'olist':
		        	    	$renderer->listo_open();
				        	foreach ($files['names'] as $filename) {
				        	    $renderer->listitem_open(1);
						        $this->_create_link($filename, $files['basedir'], $files['webdir'], $params, $renderer);
				        	    $renderer->listitem_close();
				        	}
				        	$renderer->listo_close();
		        	    	break;
		        	    	
		        	    case 'table':
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
		        	    	
		        	    	for ($i = 0; $i < count($files['names']); $i++) {
		        	    	    $filename = $files['names'][$i];
		        	    	    $filemtime = $files['mtimes'][$i];
		        	    	    $filectime = $files['ctimes'][$i];
		        	    	    $filesize = $files['sizes'][$i];
		        	    	    
				        	    $renderer->tablerow_open();
				        	    $renderer->tablecell_open();
						        $this->_create_link($filename, $files['basedir'], $files['webdir'], $params, $renderer);
				        	    $renderer->tablecell_close();
				        	    
				        	    if ($params['tableshowsize']) {
				        	        $renderer->tablecell_open(1, 'right');
				        	        $renderer->doc .= $filesize;
				        	        $renderer->tablecell_close();
				        	    }
				        	    
				        	    if ($params['tableshowdate']) {
				        	        $renderer->tablecell_open();
				        	        $renderer->doc .= strftime($conf['dformat'], $filemtime);
				        	        $renderer->tablecell_close();
				        	    }
				        	    
				        	    $renderer->tablerow_close();
				        	}
		        	    	$renderer->table_close();
		        	    	break;
		        	}
		        	$renderer->doc .= '</div>'.DOKU_LF;
		        	return true;
		            
            }
        }
        return false;
    }
    
    /**
     * Creates the downloadlink for the given filename, based on the given 
     * parameters, and adds it to the output of the renderer.
     */
    function _create_link($filename, $basedir, $webdir, $params, &$renderer) {
    	global $conf;
    	
        //prepare for formating
        $link['target'] = $conf['target']['extern'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['class']  = 'media';
    	if (!$params['direct']) {
    		$link['url'] = ml(':'.str_replace('/', ':', substr($filename, strlen($this->mediadir))));
    	} else { 
    		$link['url'] = $webdir.substr($filename, strlen($basedir));
    	}
        $link['name']   = basename($filename);
        $link['title']  = $renderer->_xmlEntities($link['url']);
        if($conf['relnofollow']) $link['more'] .= ' rel="nofollow"';

        list($ext,$mime) = mimetype(basename($filename));
        $link['class'] .= ' mediafile mf_'.$ext;
        
        //output formatted
        $renderer->doc .= $renderer->_formatLink($link);
    }
    
    /**
     * Creates the filelist based on the given glob-pattern and
     * sorting and ordering parameters.
     */
    function _create_filelist($pattern, $params) {
    	global $conf;
    	global $ID;
    	
    	// we don't want to use $conf['media'] here as that path has symlinks resolved

        $files = array(
        	'names' => array(),
        	'mtimes' => array(),
        	'ctimes' => array(),
        	'sizes' => array(),
        	'basedir' => false,
        	'webdir' => false,
        );
        
        if (!$params['direct']) {
        	// if media path is not absolute, precede it with the current namespace
        	if (substr($pattern, 0, 1) != ':')
				$pattern = ':'.getNS($ID) . ':' . $pattern;
        	// replace : with / and prepend mediadir
        	$pattern = $this->mediadir . str_replace(':', '/', $pattern);
        } else {
        	// if path is not absolute, precede it with DOKU_INC
        	if (substr($pattern, 0, 1) != '/')
				$pattern = DOKU_INC.$pattern;
        }
        // get the canonicalized basedir (without resolving symlinks)
        $dir = rp(dirname($pattern)).'/';

		// if the directory is non existant, we of course have no matches        
        if (!$dir || !file_exists($dir))
        	return DOKU_PLUGIN_FILELIST_NOMATCH;
        
        $allowed_absolute_paths = split(',', $this->getConf('allowed_absolute_paths'));
        $web_paths = split(',', $this->getConf('web_paths'));
        $basedir = false;
        $webdir = false;
        if (count($allowed_absolute_paths) == count($web_paths)) {
	        for($i = 0; $i < count($allowed_absolute_paths); $i++) {
	        	if (strstr($dir, trim($allowed_absolute_paths[$i])) == $dir) {
	        		$basedir = trim($allowed_absolute_paths[$i]);
	        		$webdir = trim($web_paths[$i]);
	        		break;
	        	}
	        }
        }
        
        // $basedir is false if $dir was not in one of the allowed paths
        if ($basedir === false)
        	return DOKU_PLUGIN_FILELIST_OUTSIDEJAIL;
        
        // glob away
        $filenames = @safe_glob($pattern);
        if (!$filenames) 
        	return DOKU_PLUGIN_FILELIST_NOMATCH;

		// retrieve fileinformation and filter out directories
		$files['basedir'] = $basedir;        	
		$files['webdir'] = $webdir;
        foreach ($filenames as $filename) {
        	if ($filename[0]=='.') continue; // exclude hidden files
            $filename = $dir.$filename;
            if (is_dir($filename)) continue; // exclude directories
            if (!$params['direct']) {        // exclude prohibited media files via ACLs
            	$mid = str_replace('/', ':', substr($filename, strlen($this->mediadir)));
            	$perm = auth_quickaclcheck($mid);
      			if ($perm < AUTH_READ) continue;    
            } else {                         // exclude not readable files
            	if (!is_readable($filename)) continue;
            }
            
            array_push($files['names'], $filename);
            array_push($files['mtimes'], filemtime($filename));
            array_push($files['ctimes'], filectime($filename));
            array_push($files['sizes'], filesize($filename));
        }

		
		$reverseflag = false;        
        if ($params['sort'] == 'mtime') {
            array_multisort($files['mtimes'], $files['names'], $files['ctimes'], $files['sizes']);
            if ($params['order'] == 'asc') $reverseflag = true;
        } else if ($params['sort'] == 'ctime') {
            array_multisort($files['ctimes'], $files['names'], $files['mtimes'], $files['sizes']);
            if ($params['order'] == 'asc') $reverseflag = true;
        } else if ($params['sort'] == 'size') {
            array_multisort($files['sizes'], $files['ctimes'], $files['mtimes'], $files['names']);
            if ($params['order'] == 'desc') $reverseflag = true;
        } else {
            array_multisort($files['names'], $files['ctimes'], $files['mtimes'], $files['sizes']);
            if ($params['order'] == 'desc') $reverseflag = true;
        }
        
        if ($reverseflag) {
        	$files['names'] = array_reverse($files['names']);
        	$files['ctimes'] = array_reverse($files['ctimes']);
        	$files['mtimes'] = array_reverse($files['mtimes']);
        	$files['sizes'] = array_reverse($files['sizes']);
        }
        
        if (count($files['names']) > 0)
        	return $files;
        else
        	return DOKU_PLUGIN_FILELIST_NOMATCH;
    }
    
      
}

/**
 * Replacement for glob() which uses fnmatch and opendir.
 *
 * @author BigueNique at yahoo dot ca
 * @see <http://www.php.net/manual/en/function.glob.php#71083>
 */
function safe_glob($pattern, $flags=0) {
    $split=explode('/',$pattern);
    $match=array_pop($split);
    $path=implode('/',$split);
    if (($dir=opendir($path))!==false) {
        $glob=array();
        while(($file=readdir($dir))!==false) {
            if (fnmatch($match,$file)) {
                if ((is_dir("$path/$file"))||(!($flags&GLOB_ONLYDIR))) {
                    if ($flags&GLOB_MARK) $file.='/';
                    $glob[]=$file;
                }
            }
        }
        closedir($dir);
        if (!($flags&GLOB_NOSORT)) sort($glob);
        return $glob;
    } else {
        return false;
    }   
}

/**
 * Replacement for fnmatch() for windows systems.
 * 
 * @author jk at ricochetsolutions dot com
 * @see <http://www.php.net/manual/en/function.fnmatch.php#71725>
 */
if(!function_exists('fnmatch')) {
    function fnmatch($pattern, $string) {
        return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'))."$#i", $string);
    }
}

/**
 * Canonicalizes a given path. A bit like realpath, but without the resolving of symlinks.
 * 
 * @author anonymous
 * @see <http://www.php.net/manual/en/function.realpath.php#73563>
 */
function rp($path) {
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
