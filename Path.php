<?php

namespace dokuwiki\plugin\filelist;

class Path
{
    protected $paths = [];

    /**
     * @param string $pathConfig The path configuration ftom the plugin settings
     */
    public function __construct($pathConfig)
    {
        $this->paths = $this->parsePathConfig($pathConfig);
    }

    /**
     * Access the parsed paths
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Parse the path configuration into an internal array
     *
     * roots (and aliases) are always saved with a trailing slash
     *
     * @return array
     */
    protected function parsePathConfig($pathConfig)
    {
        $paths = [];
        $lines = explode("\n", $pathConfig);
        $lastRoot = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, 'A>')) {
                // this is an alias for the last read root
                $line = trim(substr($line, 2));
                if (!isset($paths[$lastRoot])) continue; // no last root, no alias
                $alias = static::cleanPath($line);
                $paths[$lastRoot]['alias'] = $alias;
                $paths[$alias] = &$paths[$lastRoot]; // alias references the original
            } elseif (str_starts_with($line, 'W>')) {
                // this is a web path for the last read root
                $line = trim(substr($line, 2));
                if (!isset($paths[$lastRoot])) continue; // no last path, no web path
                $paths[$lastRoot]['web'] = $line;
            } else {
                // this is a new path
                $line = static::cleanPath($line);
                $lastRoot = $line;
                $paths[$line] = [
                    'root' => $line,
                    'web' => DOKU_BASE . 'lib/plugins/filelist/file.php?root=' . rawurlencode($line) . '&file=',
                ];
            }
        }

        return $paths;
    }

    /**
     * Check if a given path is listable and return it's configuration
     *
     * @param string $path
     * @param bool $addTrailingSlash
     * @return array
     * @throws \Exception if the given path is not allowed
     */
    public function getPathInfo($path, $addTrailingSlash = true)
    {
        $path = static::cleanPath($path, $addTrailingSlash);

        $paths = $this->paths;
        if ($paths === []) {
            throw new \Exception('No paths configured');
        }

        $allowed = array_keys($paths);
        usort($allowed, static fn($a, $b) => strlen($a) - strlen($b));
        $allowed = array_map('preg_quote_cb', $allowed);
        $regex = '/^(' . implode('|', $allowed) . ')/';

        if (!preg_match($regex, $path, $matches)) {
            throw new \Exception('Path not allowed: ' . $path);
        }
        $match = $matches[1];

        $pathInfo = $paths[$match];
        $pathInfo['local'] = substr($path, strlen($match));
        $pathInfo['path'] = $pathInfo['root'] . $pathInfo['local'];


        return $pathInfo;
    }

    /**
     * Clean a path for better comparison
     *
     * Converts all backslashes to forward slashes
     * Keeps leading double backslashes for UNC paths
     * Ensure a single trailing slash unless disabled
     *
     * @param string $path
     * @return string
     */
    public static function cleanPath($path, $addTrailingSlash = true)
    {
        if (str_starts_with($path, '\\\\')) {
            $unc = '\\\\';
        } else {
            $unc = '';
        }
        $path = ltrim($path, '\\');
        $path = str_replace('\\', '/', $path);
        $path = self::realpath($path);
        if ($addTrailingSlash) {
            $path = rtrim($path, '/');
            $path .= '/';
        }

        return $unc . $path;
    }

    /**
     * Canonicalizes a given path. A bit like realpath, but without the resolving of symlinks.
     *
     * @author anonymous
     * @see <http://www.php.net/manual/en/function.realpath.php#73563>
     */
    public static function realpath($path)
    {
        $path = explode('/', $path);
        $output = [];
        $counter = count($path);
        for ($i = 0; $i < $counter; $i++) {
            if ('.' == $path[$i]) continue;
            if ('' === $path[$i] && $i > 0) continue;
            if ('..' == $path[$i] && '..' != ($output[count($output) - 1] ?? '')) {
                array_pop($output);
                continue;
            }
            $output[] = $path[$i];
        }
        return implode('/', $output);
    }

    /**
     * Check if the given path is within the data or dokuwiki dir
     *
     * This whould prevent accidental or deliberate circumvention of the ACLs
     *
     * @param string $path and already cleaned path
     * @return bool
     */
    public static function isWikiControlled($path)
    {
        global $conf;
        $dataPath = self::cleanPath($conf['savedir']);
        if (str_starts_with($path, $dataPath)) {
            return true;
        }
        $wikiDir = self::cleanPath(DOKU_INC);
        if (str_starts_with($path, $wikiDir)) {
            return true;
        }
        return false;
    }
}
