<?php
class midgardmvc_core_route
{
    public $id = '';
    public $path = '';
    public $controller = '';
    public $action = '';
    public $template_aliases = array
    (
        'root' => 'ROOT',
        'content' => '',
    );
    public $mimetype = 'text/html';
    //public $mimetype = 'application/xhtml+xml';

    public function __construct($id, $path, $controller, $action, array $template_aliases = null)
    {
        $this->id = $id;
        $this->path = $path;
        $this->controller = $controller;
        $this->action = $action;
        if (!is_null($template_aliases))
        {
            foreach ($template_aliases as $alias => $template)
            {
                $this->template_aliases[$alias] = $template;
            }
        }
    }

    private function normalize_path()
    {
        // Normalize route
        $path = $this->path;
        if (   strpos($path, '?') === false
            && substr($path, -1, 1) !== '/')
        {
            $path .= '/';
        }
        return preg_replace('%/{2,}%', '/', $path);
    }

    public function split_path()
    {
        $path = false;
        $path_get = false;
        $path_args = false;
        
        /* This will split route from "@" - mark
         * /some/route/@somedata
         * $matches[1] = /some/route/
         * $matches[2] = somedata
         */
        preg_match('%([^@]*)@%', $this->path, $matches);
        if(count($matches) > 0)
        {
            $path_args = true;
        }
        
        $path = $this->normalize_path();
        // Get route parts
        $path_parts = explode('?', $path, 2);
        $path = $path_parts[0];
        if (isset($path_parts[1]))
        {
            $path_get = $path_parts[1];
        }
        unset($path_parts);
        return array($path, $path_get, $path_args);
    }
}
