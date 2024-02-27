<?php

namespace dokuwiki\plugin\filelist;

class Output
{
    /** @var \Doku_Renderer */
    protected $renderer;

    /** @var string */
    protected $basedir;

    /** @var string */
    protected $webdir;

    /** @var array */
    protected $files;


    public function __construct(\Doku_Renderer $renderer, $basedir, $webdir, $files)
    {
        $this->renderer = $renderer;
        $this->basedir = $basedir;
        $this->webdir = $webdir;
        $this->files = $files;
    }

    public function renderAsList($params)
    {
        if ($this->renderer instanceof \Doku_Renderer_xhtml) {
            $this->renderer->doc .= '<div class="filelist-plugin">';
        }

        $this->renderListItems($this->files, $params);

        if ($this->renderer instanceof \Doku_Renderer_xhtml) {
            $this->renderer->doc .= '</div>';
        }
    }

    /**
     * Renders the files as a table, including details if configured that way.
     *
     * @param array $params the parameters of the filelist call
     */
    public function renderAsTable($params)
    {
        if ($this->renderer instanceof \Doku_Renderer_xhtml) {
            $this->renderer->doc .= '<div class="filelist-plugin">';
        }

        $items = $this->flattenResultTree($this->files);
        $this->renderTableItems($items, $params);

        if ($this->renderer instanceof \Doku_Renderer_xhtml) {
            $this->renderer->doc .= '</div>';
        }
    }


    /**
     * Renders the files as a table, including details if configured that way.
     *
     * @param array $params the parameters of the filelist call
     */
    protected function renderTableItems($items, $params)
    {

        $renderer = $this->renderer;


        // count the columns
        $columns = 1;
        if ($params['showsize']) {
            $columns++;
        }
        if ($params['showdate']) {
            $columns++;
        }

        $renderer->table_open($columns);

        if ($params['tableheader']) {
            $renderer->tablethead_open();
            $renderer->tablerow_open();

            $renderer->tableheader_open();
            $renderer->cdata($this->getLang('filename'));
            $renderer->tableheader_close();

            if ($params['showsize']) {
                $renderer->tableheader_open();
                $renderer->cdata($this->getLang('filesize'));
                $renderer->tableheader_close();
            }

            if ($params['showdate']) {
                $renderer->tableheader_open();
                $renderer->cdata($this->getLang('lastmodified'));
                $renderer->tableheader_close();
            }

            $renderer->tablerow_close();
            $renderer->tablethead_close();
        }

        $renderer->tabletbody_open();
        foreach ($items as $item) {
            $renderer->tablerow_open();
            $renderer->tablecell_open();
            $this->renderItemLink($item, $params['randlinks']);
            $renderer->tablecell_close();

            if ($params['showsize']) {
                $renderer->tablecell_open(1, 'right');
                $renderer->cdata(filesize_h($item['size']));
                $renderer->tablecell_close();
            }

            if ($params['showdate']) {
                $renderer->tablecell_open();
                $renderer->cdata(dformat($item['mtime']));
                $renderer->tablecell_close();
            }

            $renderer->tablerow_close();
        }
        $renderer->tabletbody_close();
        $renderer->table_close();
    }


    /**
     * Recursively renders a tree of files as list items.
     *
     * @param array $items the files to render
     * @param array $params the parameters of the filelist call
     * @param int $level the level to render
     * @return void
     */
    protected function renderListItems($items, $params, $level = 1)
    {
        if ($params['style'] == 'olist') {
            $this->renderer->listo_open();
        } else {
            $this->renderer->listu_open();
        }

        foreach ($items as $file) {
            if ($file['children'] === false && $file['treesize'] === 0) continue; // empty directory

            $this->renderer->listitem_open($level);
            $this->renderer->listcontent_open();

            if ($file['children'] !== false && $file['treesize'] > 0) {
                // render the directory and its subtree
                $this->renderer->cdata($file['name']);
                $this->renderListItems($file['children'], $params, $level + 1);
            } elseif ($file['children'] === false) {
                // render the file link
                $this->renderItemLink($file, $params['randlinks']);

                // render filesize
                if ($params['showsize']) {
                    $this->renderer->cdata($params['listsep'] . filesize_h($file['size']));
                }
                // render lastmodified
                if ($params['showdate']) {
                    $this->renderer->cdata($params['listsep'] . dformat($file['mtime']));
                }
            }

            $this->renderer->listcontent_close();
            $this->renderer->listitem_close();
        }

        if ($params['style'] == 'olist') {
            $this->renderer->listo_close();
        } else {
            $this->renderer->listu_close();
        }
    }

    protected function renderItemLink($item, $cachebuster = false)
    {
        if ($this->renderer instanceof \Doku_Renderer_xhtml) {
            $this->renderItemLinkXHTML($item, $cachebuster);
        } else {
            $this->renderItemLinkAny($item, $cachebuster);
        }
    }

    /**
     * Render a file link on the XHTML renderer
     */
    protected function renderItemLinkXHTML($item, $cachebuster = false)
    {
        global $conf;
        /** @var \Doku_Renderer_xhtml $renderer */
        $renderer = $this->renderer;

        //prepare for formating
        $link['target'] = $conf['target']['extern'];
        $link['style'] = '';
        $link['pre'] = '';
        $link['suf'] = '';
        $link['more'] = '';
        $link['url'] = $this->itemWebUrl($item, $cachebuster);
        $link['name'] = $item['name'];
        $link['title'] = $renderer->_xmlEntities($link['url']);
        if ($conf['relnofollow']) $link['more'] .= ' rel="nofollow"';
        [$ext,] = mimetype(basename($item['local']));
        $link['class'] = 'media mediafile mf_' . $ext;
        $renderer->doc .= $renderer->_formatLink($link);
    }

    /**
     * Render a file link on any Renderer
     * @param array $item
     * @param bool $cachebuster
     * @return void
     */
    protected function renderItemLinkAny($item, $cachebuster = false)
    {
        $this->renderer->externalmedialink($this->itemWebUrl($item, $cachebuster), $item['name']);
    }

    /**
     * Construct the Web URL for a given item
     *
     * @param array $item The item data as returned by the Crawler
     * @param bool $cachbuster add a cachebuster to the URL?
     * @return string
     */
    protected function itemWebUrl($item, $cachbuster = false)
    {
        if (str_ends_with($this->webdir, '=')) {
            $url = $this->webdir . rawurlencode($item['local']);
        } else {
            $url = $this->webdir . $item['local'];
        }

        if ($cachbuster) {
            if (strpos($url, '?') === false) {
                $url .= '?t=' . $item['mtime'];
            } else {
                $url .= '&t=' . $item['mtime'];
            }
        }
        return $url;
    }

    /**
     * Flattens the filelist by recursively walking through all subtrees and
     * merging them with a prefix attached to the filenames.
     *
     * @param array $items the tree to flatten
     * @param string $prefix the prefix to attach to all processed nodes
     * @return array a flattened representation of the tree
     */
    protected function flattenResultTree($items, $prefix = '')
    {
        $result = [];
        foreach ($items as $file) {
            if ($file['children'] !== false) {
                $result = array_merge(
                    $result,
                    $this->flattenResultTree($file['children'], $prefix . $file['name'] . '/')
                );
            } else {
                $file['name'] = $prefix . $file['name'];
                $result[] = $file;
            }
        }
        return $result;
    }

    protected function getLang($key)
    {
        $syntax = plugin_load('syntax', 'filelist');
        return $syntax->getLang($key);
    }
}
