<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\plugin\filelist\Crawler;
use dokuwiki\plugin\filelist\Output;
use dokuwiki\plugin\filelist\Path;

/**
 * Filelist Plugin: Lists files matching a given glob pattern.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */
class syntax_plugin_filelist extends SyntaxPlugin
{
    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 222;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{filelist>.+?\}\}', $mode, 'plugin_filelist');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $INPUT;

        // do not allow the syntax in discussion plugin comments
        if (!$this->getConf('allow_in_comments') && $INPUT->has('comment')) {
            return false;
        }

        $match = substr($match, strlen('{{filelist>'), -2);
        [$path, $flags] = explode('&', $match, 2);

        // load default config options
        $flags = $this->getConf('defaults') . '&' . $flags;
        $flags = explode('&', $flags);

        $params = [
            'sort' => 'name',
            'order' => 'asc',
            'style' => 'list',
            'tableheader' => 0,
            'recursive' => 0,
            'titlefile' => '_title.txt',
            'cache' => 0,
            'randlinks' => 0,
            'showsize' => 0,
            'showdate' => 0,
            'listsep' => ', ',
        ];
        foreach ($flags as $flag) {
            [$name, $value] = sexplode('=', $flag, 2, '');
            $params[trim($name)] = trim(trim($value), '"'); // quotes can be use to keep whitespace
        }

        // separate path and pattern
        $path = Path::cleanPath($path, false);
        $parts = explode('/', $path);
        $pattern = array_pop($parts);
        $base = implode('/', $parts) . '/';

        return [$base, $pattern, $params];
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        [$base, $pattern, $params] = $data;

        if ($format != 'xhtml' && $format != 'odt') {
            return false;
        }

        // disable caching
        if ($params['cache'] === 0) {
            $renderer->nocache();
        }


        try {
            $pathHelper = new Path($this->getConf('paths'));
            $pathInfo = $pathHelper->getPathInfo($base);
        } catch (Exception $e) {
            $renderer->cdata('[n/a: ' . $this->getLang('error_outsidejail') . ']');
            return true;
        }

        $crawler = new Crawler($this->getConf('extensions'));
        $crawler->setSortBy($params['sort']);
        $crawler->setSortReverse($params['order'] === 'desc');

        $result = $crawler->crawl(
            $pathInfo['root'],
            $pathInfo['local'],
            $pattern,
            $params['recursive'],
            $params['titlefile']
        );

        // if we got nothing back, display a message
        if ($result == []) {
            $renderer->cdata('[n/a: ' . $this->getLang('error_nomatch') . ']');
            return true;
        }

        $output = new Output($renderer, $pathInfo['root'], $pathInfo['web'], $result);

        switch ($params['style']) {
            case 'list':
            case 'olist':
                $output->renderAsList($params);
                break;
            case 'table':
                $output->renderAsTable($params);
                break;
        }
        return true;
    }
}
