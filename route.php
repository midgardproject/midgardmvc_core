<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC route class
 *
 * @package midgardmvc_core
 */
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

    /**
     * Constructor
     *
     * The path can have variables in the following fashion:
     *
     * /path/to/route/ (route with static arguments)
     * /path/to/{$varname}/ (with variable argument)
     * /path/to/{$varname}/{$varname2}/ (with two variable arguments)
     * /path/to/{$(int|float|guid):varname}/ (variable argument with type hinting/checking, NOTE: string is the implicit default type)
     * /path/to/@ (with variable lenght argument list at end of path)
     *
     * @param string $id route identifier
     * @param string $path route path (see details about the path definition)
     * @param string $controller controller class name 
     * @param string $action action method name
     * @param array $template_aliases keys are template names to override, values template names to use in their place
     * @param string $mimetype mimetype header to set
     */
    public function __construct($id, $path, $controller, $action, array $template_aliases, $mimetype = 'application/xhtml+xml')
    {
        $this->id = $id;
        $this->path = $path;
        $this->controller = $controller;
        $this->action = $action;
        foreach ($template_aliases as $alias => $template)
        {
            $this->template_aliases[$alias] = $template;
        }
        $this->mimetype = $mimetype;
    }

    public function set_variables(array $arguments)
    {
        $path = $this->path;
        foreach ($arguments as $key => $value)
        {
            if (is_array($value))
            {
                if ($key == 'variable_arguments')
                {
                    if (strpos($path, '@') === false)
                    {
                        continue;
                    }
                    $path = str_replace('@', '/' . implode('/', $value), $path);
                    continue;
                }
                $value_array = array();
                foreach ($value as $part)
                {
                    if (empty($part))
                    {
                        continue;
                    }
                    $value_array[] = $part;
                }

                $value = implode('.', $value_array);

                // This is a token replacement, add the type hint
                $key = "token:{$key}";
            }

            $path_backup = (string)$path;
            $type = gettype($value);
            switch($type)
            {
                case 'integer':
                    $path = str_replace(array("{\${$key}}", "{\$int:{$key}}"), $value, $path);
                    break;
                case 'float':
                case 'double':
                    $path = str_replace(array("{\${$key}}", "{\$float:{$key}}"), $value, $path);
                    break;
                case 'string':
                    if (mgd_is_guid($value))
                    {
                        $path = str_replace(array("{\${$key}}", "{\$guid:{$key}}"), $value, $path);
                    }
                    else
                    {
                        $path = str_replace(array("{\${$key}}", "{\$string:{$key}}"), $value, $path);
                    }
                    break;
            }
            if ($path_backup === $path)
            {
                throw new InvalidArgumentException("Argument '{$key}' could not be placed, likely the value is of wrong type");
            }
        }

        // PONDER: what is wrong with the mb_ereg here (todo: check posix vs perl flavor differences)
        //if (mb_ereg_match('\{\$([^}]+)\}', $path))
        if (preg_match('%\{\$[^}]+\}%', $path))
        {
            throw new UnexpectedValueException("Missing arguments for route '{$this->id}'");
        }

        return explode('/', $path);
    }

    /**
     * Check if the route matches to a given request path
     *
     * Returns NULL for no match, or an array of arguments for a match
     */
    public function check_match($argv_str, array $query = null)
    {
        list ($route_path, $route_get, $route_args) = $this->split_path();

        if (is_null($query))
        {
            $query = array();
        }

        $route_path_matches = array();
        if (!preg_match_all('%\{\$(.+?)\}%', $route_path, $route_path_matches))
        {
            // Simple route (only static arguments)
            if ($route_path === $argv_str)
            {
                if (!$route_get)
                {
                    // echo "DEBUG: simple match route_id:{$route_id}\n";
                    return array();
                }
                else
                {
                    return $this->check_match_get($route_get, $query, $argv_str);
                }
            }
    

            if ($route_args) // Route @ set
            {
                $path = explode('@', $route_path);
                $args_regex = '^' . str_replace('@', '(.+?)', mb_ereg_replace('\{(.+?)\}', '[^/]+?', $route_path)) . '$';
                //echo "DEBUG: args_regex:{$args_regex} argv_str:{$argv_str}\n";
                if (mb_ereg($args_regex, $argv_str, $args_matches))
                {
                    /*
                    echo "DEBUG: args_matches\n";
                    var_dump($args_matches);
                    */
                    $matched = array();
                    if (substr($args_matches[1], -1, 1) == '/')
                    {
                        $args_matches[1] = substr($args_matches[1], 0, -1);
                    }
                    $matched['variable_arguments'] = explode('/', $args_matches[1]);
                    return $matched;
                }
            }
            // Did not match
            return null;
        }
        // "complex" route (with variable arguments)
        //echo "DEBUG: route_args:{$route_args}  route_path:{$route_path} this->path:{$this->path}\n";
        if (strpos($route_path, '@') !== false)
        {
            $route_path_regex = '^' . str_replace('@', '.*?', mb_ereg_replace('\{(.+?)\}', '([^/]+?)', $route_path)) . '$';
        }
        else 
        {
            $route_path_regex = '^' . mb_ereg_replace('\{(.+?)\}', '([^/]+?)', $route_path) . '$';
        }
        //echo "DEBUG: route_path_regex:{$route_path_regex} argv_str:{$argv_str}\n";
        $route_path_regex_matches = array();
        if (!mb_ereg($route_path_regex, $argv_str, $route_path_regex_matches))
        {
            // Does not match, NEXT!
            return null;
        }

        /*
        echo "DEBUG: route_path_regex_matches\n";
        var_dump($route_path_regex_matches);
        */

        if ($route_args) // Route @ set
        {
            $path = explode('@', $route_path);
            $args_regex = '^' . str_replace('@', '(.+?)', mb_ereg_replace('\{(.+?)\}', '[^/]+?', $route_path)) . '$';
            //echo "DEBUG: args_regex:{$args_regex} argv_str:{$argv_str}\n";
            if (mb_ereg($args_regex, $argv_str, $args_matches))
            {
                /*
                echo "DEBUG: args_matches\n";
                var_dump($args_matches);
                */
                $variable_matched = explode('/', $args_matches[1]);
            }
        }

        if ($route_get)
        {
            $get_matched = $this->check_match_get($route_get, $query, $argv_str);
            if (!$get_matched)
            {
                // We have GET part that could not be matched, NEXT!
                return null;
            }
            // Set matched GET arguments to the vars being handled
        }

        // Remove the first item that is the full path
        array_shift($route_path_regex_matches);

        // We have a complete match, setup route_id arguments and return
        $matched = $this->normalize_variables($route_path_matches[1], $route_path_regex_matches, $argv_str);
        if ($route_get)
        {
            $matched = array_merge($matched, $get_matched);
            unset($get_matched);
        }
        if (   $route_args
            && isset($variable_matched))
        {
            $matched['variable_arguments'] = $variable_matched;
            unset($variable_matched);
        }
        /*
        echo "DEBUG: matched\n";
        var_dump($matched);
        */

        return $matched;
    }

    /**
     * Checks GET part of a route definition and places arguments as needed
     *
     * @access private
     * @param string $route_get GET part of a route definition
     * @return array of matched GET arguments mapped to their values, or null for no match
     *
     * @fixme Move action arguments to subarray
     */
    private function check_match_get($route_get, array $query, $argv_str)
    {
        if (!preg_match_all('%\&?(.+?)=\{(.+?)\}%', $route_get, $route_get_matches))
        {
            // Can't parse arguments from route_get
            throw new UnexpectedValueException("GET part of route '{$this->id}' ('{$route_get}') cannot be parsed");
        }

        /*
        echo "DEBUG: route_get_matches\n===\n";
        print_r($route_get_matches);
        echo "===\n";
        */
        $matches = array();
        foreach ($route_get_matches[1] as $index => $get_key)
        {
            //echo "this->get[{$get_key}]:{$this->get[$get_key]}\n";
            if (   !is_array($query)
                || !isset($query[$get_key])
                || empty($query[$get_key]))
            {
                // required GET parameter not present, return false;
                return null;
            }
            $matches[] = $query[$get_key];
        }

        // Unlike in route_matches falling through means match

        return $this->normalize_variables($route_get_matches[1], $matches, $argv_str);
    }

    private function tokenize_argument($argument)
    {
        $tokens = array
        (
            'identifier' => '',
            'variant'    => '',
            'language'   => '',
            'type'       => 'html',
        );
        $argument_parts = explode('.', $argument);

        // First part is always identifier
        $tokens['identifier'] = $argument_parts[0];

        if (count($argument_parts) == 2)
        {
            // If there are two parts, the second is type
            $tokens['type'] = $argument_parts[1];
        }
        
        if (count($argument_parts) >= 3)
        {
            // If there are three parts, then second is variant and third is type
            $tokens['variant'] = $argument_parts[1];
            $tokens['type'] = $argument_parts[2];
        }

        if (count($argument_parts) >= 4)
        {
            // If there are four or more parts, then third is language and fourth is type
            $tokens['language'] = $argument_parts[2];
            $tokens['type'] = $argument_parts[3];
        }
        
        return $tokens;
    }

    private function normalize_variables($variables, $values, $argv_str)
    {
        // Map variable arguments
        $matched = array();
        foreach ($variables as $index => $varname)
        {
            $variable_parts = explode(':', $varname);
            if (count($variable_parts) == 1)
            {
                $type_hint = '';
            }
            else
            {
                $type_hint = $variable_parts[0];
            }
                            
            // Strip type hints from variable names
            $varname = mb_ereg_replace('^.+:', '', $varname);
            
            if ($type_hint == 'token')
            {
                // Tokenize the argument to handle resource typing
                $matched[$varname] = $this->tokenize_argument($values[$index]);
            }
            else
            {
                $value = $values[$index];
                switch($type_hint)
                {
                    case 'string':
                    case '':
                        $matched[$varname] = $value;
                        break;

                    case 'guid':
                        if (!mgd_is_guid($value))
                        {
                            throw new InvalidArgumentException("Variable '{$varname}' is type hinted as '{$type_hint}' but parsed value '{$value}' is not guid");
                        }
                        $matched[$varname] = $value;
                        break;

                    case 'int':
                        if (!is_numeric($value))
                        {
                            throw new InvalidArgumentException("Variable '{$varname}' is type hinted as '{$type_hint}' but parsed value '{$value}' is not numeric");
                        }
                        $matched[$varname] = (int) $value;
                        break;

                    case 'float':
                    case 'double':
                        if (!is_numeric($value))
                        {
                            throw new InvalidArgumentException("Variable '{$varname}' is type hinted as '{$type_hint}' but parsed value '{$value}' is not numeric");
                        }
                        $matched[$varname] = (double) $value;
                        break;

                    default:
                        throw new InvalidArgumentException("Variable '{$varname}' is type hinted as '{$type_hint}', but he hint is not understood");
                }
            }

        }

        return $matched;
    }

    /** 
     * Normalizes the given path
     *
     */
    private function normalize_path($path)
    {
        // Make sure the @ has a preceding slash
        if (   ($at_position = strpos($path, '@')) !== false
            && substr($path, max(array($at_position-1,0)), 2) !== '/@')
        {
            $path = str_replace('@', '/@', $path);
        }
        // If path has no GET variables and does not end in trailing slash (or @), add trailing slash
        if (   strpos($path, '?') === false
            && substr($path, -1, 1) !== '/'
            && substr($path, -1, 1) !== '@')
        {
            $path .= '/';
        }

        // Convert doubled slashes to single ones
        return mb_ereg_replace('/{2,}', '/', $path);
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
        mb_ereg('([^@]*)@', $this->path, $matches);
        if(count($matches) > 0)
        {
            $path_args = true;
        }
        
        $path = $this->normalize_path($this->path);
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
?>
