<?php

class midgardmvc_core_services_dispatcher_appserv extends midgardmvc_core_services_dispatcher_midgard3
{
    private $appserver_context = null;
    private $headers = array();
    private $status = 200;

    public function set_request_data($appserver_context)
    {
        $this->appserver_context = $appserver_context;

        // reset
        $this->headers = array();
        $this->status = 200;
    }

    /**
     * Parse request URL into components and return a corresponding MVC request object
     *
     * @return midgardmvc_core_helpers_request
     */
    public function get_request()
    {
        $request = new midgardmvc_core_helpers_request();
        $request->set_root_node($this->_root_node);
        $request->set_method($this->appserver_context['env']['REQUEST_METHOD']);

        // Parse URL into components (Mjolnir doesn't do this for us)
        $url_components = parse_url("http://{$this->appserver_context['env']['HTTP_HOST']}{$this->appserver_context['env']['REQUEST_URI']}");

        // Handle GET parameters
        if (!empty($url_components['query']))
        {
            $get_parameters = array();
            parse_str($url_components['query'], $get_parameters);
            $request->set_query($get_parameters);
        }

        $request->resolve_node($url_components['path']);

        return $request;
    }

    public function header($string, $replace = true, $http_response_code = null)
    {
        if (strpos($string, 'HTTP/1.0 ') === 0 or strpos($string, 'HTTP/1.1 ') === 0) {
            $this->status = intval(substr($string, 9, 3));
            return;
        }

        $pair = explode(': ', $string, 2);

        if ($replace === true)
        {
            foreach ($this->headers as $i => $_pair)
            {
                if ($_pair[0] == $pair[0])
                {
                    unset($this->headers[$i]);
                }
            }
        }

        if ($pair[0] == 'Status' and is_numeric(substr($pair[1], 0, 3))) {
            $this->status = intval(substr($pair[1], 0, 3));
            return;
        }

        $this->headers[] = $pair;

        if (is_numeric($http_response_code))
        {
            $this->status = intval($http_response_code);
        }
        elseif (strtolower($pair[0]) == 'location')
        {
            if ($this->status == 201 or ($this->status >= 300 and $this->status < 400))
            {
                return;
            }

            $this->status = 302;
        }
    }

    public function headers_sent()
    {
        // it's never too late in appserver
        return false;
    }

    public function setcookie($name, $value = '', $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = false)
    {
        return $this->appserver_context['_COOKIE']->setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function session_start()
    {
        if (!isset($this->appserver_context['mfs.session']))
            throw new LogicException('Session middleware is not available');

        $this->appserver_context['mfs.session']->start();
        $this->session_is_started = true;

        return true;
    }

    public function session_has_var($name)
    {
        return isset($this->appserver_context['mfs.session']->$name);
    }

    public function session_get_var($name)
    {
        return $this->appserver_context['mfs.session']->$name;
    }

    public function session_set_var($name, $value)
    {
        $this->appserver_context['mfs.session']->$name = $value;
    }

    public function session_commit()
    {
        $this->appserver_context['mfs.session']->save();
        $this->session_is_started = false;
    }

    public function end_request()
    {
        throw new StartNewRequestException();
    }

    public function _get_status()
    {
        return $this->status;
    }

    public function _get_headers()
    {
        $headers = array();
        foreach ($this->headers as $pair) {
            $headers[] = $pair[0];
            $headers[] = $pair[1];
        }

        return $headers;
    }
}
