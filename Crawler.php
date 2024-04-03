<?php

namespace dokuwiki\plugin\filelist;

class Crawler
{
    /** @var string regexp to check extensions */
    protected $ext;

    /** @var string */
    protected $sortby = 'name';

    /** @var bool */
    protected $sortreverse = false;

    /** @var string[] patterns to ignore */
    protected $ignore = [];

    /**
     * Initializes the crawler
     *
     * @param string $extensions The extensions to allow (comma separated list)
     */
    public function __construct($extensions)
    {
        $this->ext = explode(',', $extensions);
        $this->ext = array_map('trim', $this->ext);
        $this->ext = array_map('preg_quote_cb', $this->ext);
        $this->ext = implode('|', $this->ext);

        $this->ignore = $this->loadIgnores();
    }

    public function setSortBy($sortby)
    {
        $this->sortby = $sortby;
    }

    public function setSortReverse($sortreverse)
    {
        $this->sortreverse = $sortreverse;
    }

    /**
     * Does a (recursive) crawl for finding files based on a given pattern.
     * Based on a safe glob reimplementation using fnmatch and opendir.
     *
     * @param string $path the path to search in
     * @param string $pattern the pattern to match to
     * @param bool $recursive whether to search recursively
     * @param string $titlefile the name of the title file
     * @return array a hierarchical filelist or false if nothing could be found
     *
     * @see http://www.php.net/manual/en/function.glob.php#71083
     */
    public function crawl($root, $local, $pattern, $recursive, $titlefile)
    {
        $path = $root . $local;

        // do not descent into wiki or data directories
        if (Path::isWikiControlled($path)) return [];

        if (($dir = opendir($path)) === false) return [];
        $result = [];
        while (($file = readdir($dir)) !== false) {
            if ($file[0] == '.' || $file == $titlefile) {
                // ignore hidden, system and title files
                continue;
            }
            $self = $local . '/' . $file;
            $filepath = $path . '/' . $file;
            if (!is_readable($filepath)) continue;

            if ($this->fnmatch($pattern, $file) || (is_dir($filepath) && $recursive)) {
                if (!is_dir($filepath) && !$this->isExtensionAllowed($file)) {
                    continue;
                }
                if ($this->isFileIgnored($file)) {
                    continue;
                }

                // get title file
                $filename = $file;
                if (is_dir($filepath)) {
                    $title = $filepath . '/' . $titlefile;
                    if (is_readable($title)) {
                        $filename = io_readFile($title, false);
                    }
                }

                // prepare entry
                if (!is_dir($filepath) || $recursive) {
                    $entry = [
                        'name' => $filename,
                        'local' => $self,
                        'path' => $filepath,
                        'mtime' => filemtime($filepath),
                        'ctime' => filectime($filepath),
                        'size' => filesize($filepath),
                        'children' => ((is_dir($filepath) && $recursive) ?
                            $this->crawl($root, $self, $pattern, $recursive, $titlefile) :
                            false
                        ),
                        'treesize' => 0,
                    ];

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
        return $this->sortItems($result);
    }

    /**
     * Sort the given items by the current sortby and sortreverse settings
     *
     * @param array $items
     * @return array
     */
    protected function sortItems($items)
    {
        $callback = [$this, 'compare' . ucfirst($this->sortby)];
        if (!is_callable($callback)) return $items;

        usort($items, $callback);
        if ($this->sortreverse) {
            $items = array_reverse($items);
        }
        return $items;
    }

    /**
     * Check if a file is allowed by the configured extensions
     *
     * @param string $file
     * @return bool
     */
    protected function isExtensionAllowed($file)
    {
        if ($this->ext === '') return true; // no restriction
        return preg_match('/(' . $this->ext . ')$/i', $file);
    }

    /**
     * Check if a file is ignored by the ignore patterns
     *
     * @param string $file
     * @return bool
     */
    protected function isFileIgnored($file)
    {
        foreach ($this->ignore as $pattern) {
            if ($this->fnmatch($pattern, $file)) return true;
        }
        return false;
    }

    /**
     * Load the ignore patterns from the ignore.txt file
     *
     * @return string[]
     */
    protected function loadIgnores()
    {
        $file = __DIR__ . '/conf/ignore.txt';
        $ignore = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ignore = array_map(static fn($line) => trim(preg_replace('/\s*#.*$/', '', $line)), $ignore);
        $ignore = array_filter($ignore);
        return $ignore;
    }

    /**
     * Replacement for fnmatch() for windows systems.
     *
     * @author jk at ricochetsolutions dot com
     * @link http://www.php.net/manual/en/function.fnmatch.php#71725
     */
    protected function fnmatch($pattern, $string)
    {
        return preg_match(
            "#^" . strtr(
                preg_quote($pattern, '#'),
                [
                    '\*' => '.*',
                    '\?' => '.',
                    '\[' => '[',
                    '\]' => ']'
                ]
            ) . "$#i",
            $string
        );
    }

    public function compareName($a, $b)
    {
        return strcmp($a['name'], $b['name']);
    }

    public function compareIname($a, $b)
    {
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    }

    public function compareCtime($a, $b)
    {
        return $a['ctime'] <=> $b['ctime'];
    }

    public function compareMtime($a, $b)
    {
        return $a['mtime'] <=> $b['mtime'];
    }

    public function compareSize($a, $b)
    {
        return $a['size'] <=> $b['size'];
    }
}
