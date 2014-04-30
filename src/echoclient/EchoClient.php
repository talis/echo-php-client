<?php
namespace echoclient;

use \Guzzle\Http\Client;
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

/**
 * Sends events to Echo, if an echo server is enabled.
 */
class EchoClient
{
    const ECHO_API_VERSION = 1;

    private $personaClient;
    private $debugEnabled = false;
    private static $logger;

    function __construct()
    {
        /*
         * The calling project needs to have already set these up.
         */

        if (!defined('OAUTH_USER'))
        {
            throw new \Exception('Missing define: OAUTH_USER');
        }

        if (!defined('OAUTH_SECRET'))
        {
            throw new \Exception('Missing define: OAUTH_SECRET');
        }

        if (!defined('PERSONA_HOST'))
        {
            throw new \Exception('Missing define: PERSONA_HOST');
        }

        if (!defined('PERSONA_OAUTH_ROUTE'))
        {
            throw new \Exception('Missing define: PERSONA_OAUTH_ROUTE');
        }

        if (!defined('PERSONA_TOKENCACHE_HOST'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_HOST');
        }

        if (!defined('PERSONA_TOKENCACHE_PORT'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_PORT');
        }

        if (!defined('PERSONA_TOKENCACHE_DB'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_DB');
        }
    }

    function createEvent($class, $source, array $props=array())
    {
        $baseUrl = $this->getBaseUrl();

        if (!$baseUrl)
        {
            /*
             * If no echo server is defined then log the event for debugging purposes and fail silently...
             */
            $this->getLogger()->warning('Echo server is not defined (missing ECHO_HOST define), not sending event - '.$class, $props);
            return false;
        }

        $eventUrl = $baseUrl.'/events';
        $eventJson = $this->getEventJson($class, $source, $props);
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken(OAUTH_USER, OAUTH_SECRET);
        $personaToken = $arrPersonaToken['access_token'];

        if ($this->isDebugEnabled())
        {
            // We want this logged as warning so we always see it (if debug mode is enabled for this class, which should only ever be used in development)
            $this->getLogger()->warning('EchoClient - using Persona token = '.$personaToken);
        }

        $headers = array(
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$personaToken
        );

        try
        {
            $client = $this->getHttpClient();
            $request = $client->post($eventUrl, $headers, $eventJson, array('connect_timeout'=>2));
            $response = $request->send();

            if ($response->isSuccessful())
            {
                $this->getLogger()->debug('Success sending event to echo - '.$class, $props);
                return true;
            }
            else
            {
                $this->getLogger()->warning('Failed sending event to echo - '.$class, array('responseCode'=>$response->getStatusCode(), 'responseBody'=>$response->getBody(true), 'requestProperties'=>$props));
                return false;
            }
        }
        catch (\Exception $e)
        {
            // For any exception issue, just log the issue and fail silently.  E.g. failure to connect to echo server, or whatever.
            $this->getLogger()->warning('Failed sending event to echo - '.$class, array('exception'=>get_class($e), 'message'=>$e->getMessage(), 'requestProperties'=>$props));
            return false;
        }
    }

    /**
     * Enable debug mode for this client.  If this is enabled we log things like the Persona client.
     * Only use in development, not production!
     * @param bool $bDebugEnabled Whether debug mode should be enabled or not (default = false)
     */
    public function setDebugEnabled($bDebugEnabled)
    {
        $this->debugEnabled = $bDebugEnabled;
    }

    /**
     * Is debugging enabled for this class?
     * We log out things like the Persona token etc.  (Only to be used in development!)
     * @return bool
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * Allow the calling project to use its own instance of a MonoLog Logger class.
     *
     * @param Logger $logger
     */
    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
    }

    protected function getEventJson($class, $source, array $props=array())
    {
        return json_encode(array('class'=>$class, 'source'=>$source, 'props'=>$props));
    }

    protected function getBaseUrl()
    {
        if (!defined('ECHO_HOST'))
        {
            return false;
        }

        return ECHO_HOST.'/'.self::ECHO_API_VERSION;
    }

    /**
     * To allow mocking of the Guzzle client for testing.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        return new Client();
    }

    /**
     * To allow mocking of the PersonaClient for testing.
     *
     * TODO This would be better as a trait as it's duplicated in BaseModel.
     *
     * @return \personaclient\PersonaClient
     */
    protected function getPersonaClient()
    {
        if (!isset($this->personaClient)) {
            $this->personaClient = new \personaclient\PersonaClient(array(
                'persona_host' => PERSONA_HOST,
                'persona_oauth_route' => PERSONA_OAUTH_ROUTE,
                'tokencache_redis_host' => PERSONA_TOKENCACHE_HOST,
                'tokencache_redis_port' => PERSONA_TOKENCACHE_PORT,
                'tokencache_redis_db' => PERSONA_TOKENCACHE_DB,
            ));
        }
        return $this->personaClient;
    }

    /**
     * Get the current Logger instance.
     *
     * @return Logger
     */
    protected function getLogger()
    {
        if (self::$logger == null)
        {
            /*
             * If an instance of the MonoLog Logger hasn't been passed in then default to stderr.
             */
            self::$logger = new Logger('echoclient');
            self::$logger->pushHandler(new StreamHandler('/tmp/echo-client.log', Logger::DEBUG));
        }

        return self::$logger;
    }
}