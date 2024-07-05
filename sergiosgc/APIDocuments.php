<?php 
declare(strict_types=1);
namespace sergiosgc;
class APIDocuments {
    public $title = null;
    public $css = null;
    public $index = null;
    public $entryPoints = null;
    public $summary = null;
    public $footnotes = null;
    public $verbDocs = null;
    public $footer = null;
    public $pageTemplate = null;
    public static $commonMarkConfig = [
        'renderer' => [
            'block_separator' => "\n",
            'inner_separator' => "\n",
            'soft_break'      => "\n",
        ],
        'commonmark' => [
            'enable_em' => true,
            'enable_strong' => true,
            'use_asterisk' => true,
            'use_underscore' => true,
            'unordered_list_markers' => ['-', '*', '+'],
        ],
        'html_input' => 'allow',
        'allow_unsafe_links' => true,
        'max_nesting_level' => PHP_INT_MAX,
        'slug_normalizer' => [
            'max_length' => 255,
        ],
    ];

    public static function createForURI(string $restRoot, string $uri) {
        $verbDocs = [ 'GET' => 'get.php', 'POST' => 'post.php', 'PUT' => 'put.php', 'DELETE' => 'delete.php'];
        foreach($verbDocs as $verb => $file) {
            $verbDocs[$verb] = realpath(sprintf('%s/%s%s', $restRoot, $uri, $file));
        }
        $verbDocs = array_filter($verbDocs);
        foreach(array_keys($verbDocs) as $verb) if (strlen($verbDocs[$verb]) < strlen(realpath(\app\app()->paths['rest']))) unset($verbDocs['verb']);
        foreach(array_keys($verbDocs) as $verb) if (realpath(\app\app()->paths['rest']) != substr($verbDocs[$verb], 0, strlen(realpath(\app\app()->paths['rest'])))) unset($verbDocs['verb']);
        foreach($verbDocs as $verb => $file) $verbDocs[$verb] = static::extractDocumentationFromFile($file);
        foreach(array_keys($verbDocs) as $verb) $verbDocs[$verb] = [
            'html' => $verbDocs[$verb],
            'uri' => htmlentities(static::parameterizeUri($restRoot, $uri, $verb))
        ];
        if (isset($verbDocs['POST'])) $verbDocs['POST']['uri'] = preg_replace('_/&lt;[^/]*&gt;$_', '', $verbDocs['POST']['uri']);

        $summary = '';
        if (realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.summary.rst"))) {
            $summary =(new \League\CommonMark\GithubFlavoredMarkdownConverter(static::$commonMarkConfig))->convertToHtml(file_get_contents( realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.summary.rst")) ));
        }
        if ($summary) $summary = sprintf('<div id="summary"><h2>%s</h2>%s</div>', __('Summary'), $summary);

        $footnotes = '';
        if (realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.footnotes.rst"))) {
            $footnotes =(new \League\CommonMark\GithubFlavoredMarkdownConverter(static::$commonMarkConfig))->convertToHtml(file_get_contents( realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.footnotes.rst")) ));
        }
        if ($footnotes) $footnotes = sprintf('<div id="footnotes">%s</div>', $footnotes);

        if (array_reduce( $verbDocs, function($acc, $verbDoc) {
            if ($acc === true) return $verbDoc['uri'];
            if ($acc === false) return false;
            return $acc == $verbDoc['uri'] ? $verbDoc['uri'] : false;
        }, true)) {
            $entrypoints = sprintf(<<<EOS
<h2>%s</h2>
<dl id="entrypoints" class="single-entrypoint">
 <dt>%s</dt>
 <dd>%s</dt>
</dl>
EOS
                , __('Entrypoint'), count($verbDocs) == 1 ? array_keys($verbDocs)[0] : __('All verbs'), count($verbDocs) ? $verbDocs[array_keys($verbDocs)[0]]['uri'] : $uri);
        } else {
            $entrypoints = sprintf(<<<EOS
<h2>%s</h2>
<dl id="entrypoints" class="multiple-entrypoints">
%s
</dl>
EOS
                , __('Entrypoints'), implode("\n", array_map(
                    function($verb, $verbDoc) { return sprintf('<dt>%s</dt><dd>%s</dd>', $verb, $verbDoc['uri']); }, 
                    array_keys($verbDocs),
                    $verbDocs))
                );
        }
        if (count($verbDocs)) {
            $verbDocs = sprintf(<<<EOS
<h2>%s</h2>            
<dl id="verbs">
 %s
</dl>
EOS
                , __('HTTP Verbs'), implode("\n", array_map( function($verb, $verbDoc) {
                    return sprintf('<dt>%s</dt><dd><span class="entrypoint">%s</span><span class="description">%s</span></dd>', $verb, $verbDoc['uri'], $verbDoc['html']);
                },
                    array_keys($verbDocs),
                    $verbDocs
                ))
            );
        } else {
            $verbDocs = '';
        }
        $page = "";
        if (realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.page.rst"))) {
            $page =(new \League\CommonMark\GithubFlavoredMarkdownConverter(static::$commonMarkConfig))->convertToHtml(file_get_contents( realpath(sprintf('%s/%s%s', $restRoot, $uri, "docs.page.rst")) ));
        }
        $result = new APIDocuments();
        $result->verbDocs = $verbDocs;
        $result->summary = $summary;
        $result->footnotes = $footnotes;
        $result->entryPoints = $entrypoints;
        $result->page = $page;
        $result->index = "";

        return $result;
    }
    public function generatePage() {
        if ($this->page) {
            return sprintf($this->pageTemplate ?? <<<EOS
<html>
 <head>
  <title>%<title></title>
  <link href="%<css>" media="screen, projection" rel="stylesheet" type="text/css">
 </head>
 <body>
<div id="index">%<index></div>
<div id="content">%<entrypoints> %<summary> %<verbDocs> %<footnotes></div>
<div id="footer">%<footer></div>
 </body>
</html>
EOS, 
            [
                'title' => $this->title ?? __("Documentation"), 
                'css' => $this->css ?? "/stylesheets/documentation.css",
                'index' => $this->index ?? "",
                'entrypoints' => "",
                'summary' => $this->page,
                'footnotes' => $this->footnotes,
                'verbDocs' => "",
                'footer' => $this->footer ?? ""
            ]);
        }
        return sprintf($this->pageTemplate ?? <<<EOS
<html>
 <head>
  <title>%<title></title>
  <link href="%<css>" media="screen, projection" rel="stylesheet" type="text/css">
 </head>
 <body>
<div id="index">%<index></div>
<div id="content">%<entrypoints> %<summary> %<verbDocs> %<footnotes></div>
<div id="footer">%<footer></div>
 </body>
</html>
EOS, 
        [
            'title' => $this->title ?? __("Documentation"), 
            'css' => $this->css ?? "/stylesheets/documentation.css",
            'index' => $this->index ?? "",
            'entrypoints' => $this->entryPoints ?? "",
            'summary' => $this->summary ?? "",
            'footnotes' => $this->footnotes ?? "",
            'verbDocs' => $this->verbDocs ?? "",
            'footer' => $this->footer ?? ""
        ]);
    }
    protected static function parameterizeUri($restRoot, $uri, $verb) {
        $uriParts = explode("/", $uri);
        $result = "";
        $path = $restRoot;
        while(count($uriParts)) {
            $part = array_shift($uriParts);
            if ($part == "") continue;
            $path .= "/" . $part;
            $result .= "/" . $part;
            if (file_exists(sprintf("%s/%s.regex", $path, strtolower($verb))) || file_exists(sprintf("%s/all.regex", $path))) {
                $regex = substr(file_get_contents(sprintf("%s/%s.regex", $path, strtolower($verb)) ? sprintf("%s/%s.regex", $path, strtolower($verb)) : sprintf("%s/all.regex", $path)), 1, -1);
                $regex = implode("|", array_map( function($regex) {
                    if (!preg_match("_\(\?(?<parameter><[^>]*>)_", $regex, $matches)) return $regex;
                    return $matches['parameter'];
                }, explode("|", $regex)));
                $result .= "/" . $regex;
            }
        }
        return $result;
    }
    protected static function traverseASTInOrder(\ast\Node $node) {
        $result = [$node];
        foreach($node->children as $child) if ($child instanceof \ast\Node) $result = array_merge($result, static::traverseASTInOrder($child));
        return $result;
    }
    protected static function extractDocumentationFromFile($file) {
        $result = array_filter(
            array_map(
                function($node) { return $node->children['docComment']; }, 
                array_filter(static::traverseASTInOrder(\ast\parse_file($file, 80)), function($node) { return isset($node->children['docComment']); })
            ),
            function($docComment) { 
                return substr($docComment, 0, strlen("/** api-documents")) == "/** api-documents"; 
            }
        );

        $result = count($result) ? array_pop($result) : false;
        if (!$result) return false;
        $result = substr($result, strlen("/** api-documents"), -strlen("*/"));
        return (new \League\CommonMark\GithubFlavoredMarkdownConverter(static::$commonMarkConfig))->convertToHtml($result);
    }
}
