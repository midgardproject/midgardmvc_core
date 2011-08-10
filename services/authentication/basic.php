<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * HTTP Basic authentication service for Midgard MVC
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_services_authentication_basic extends midgardmvc_core_services_authentication_midgard2
{
    public function __construct()
    {
        parent::__construct();
    }

    public function check_session()
    {
        $this->user = null;
        $this->person = null;
        if (   isset($_SERVER['PHP_AUTH_USER'])
            && isset($_SERVER['PHP_AUTH_PW']))
        {
            $tokens = array
            (
                'login' => $_SERVER['PHP_AUTH_USER'],
                'password' => $_SERVER['PHP_AUTH_PW'],
            );
            $this->login($tokens);
        }
    }

    public function login(array $tokens)
    {
        if (!isset($tokens['login']))
        {
            throw new InvalidArgumentException("Login tokens need to provide a login");
        }

        return $this->do_midgard_login($tokens);
    }

    public function logout()
    {
        // TODO: Can this be implemented for Basic auth?
        return;
    }

    public function handle_exception(Exception $exception)
    {
        if ( ! (isset($_SERVER['PHP_AUTH_USER'])
            && isset($_SERVER['PHP_AUTH_PW']))
            && isset($_SERVER['HTTP_AUTHORIZATION']))
        {
            $auth_params = explode(":", base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            $_SERVER['PHP_AUTH_USER'] = $auth_params[0];
            unset($auth_params[0]);
            $_SERVER['PHP_AUTH_PW'] = implode('', $auth_params);
        }

        if (   !isset($_SERVER['PHP_AUTH_USER'])
            || !isset($_SERVER['PHP_AUTH_PW']))
        {
            $app = midgardmvc_core::get_instance();
            $app->dispatcher->header("WWW-Authenticate: Basic realm=\"MidgardMVC\"");
            $app->dispatcher->header('HTTP/1.0 401 Unauthorized');
            // TODO: more fancy 401 output ?
            echo "<h1>Authorization required</h1>\n";
            // Clean up the context
            $app->context->delete();
            $app->dispatcher->end_request();
        }

        $tokens = array
        (
            'login' => $_SERVER['PHP_AUTH_USER'],
            'password' => $_SERVER['PHP_AUTH_PW'],
        );

        if (!$this->login($tokens))
        {
            // Wrong password: Recurse until auth ok or user gives up
            unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
            $this->handle_exception($exception);
        }
    }
}
?>
