<?php
if (!defined('APPROOT'))
{
    define('APPROOT', dirname(dirname(__DIR__)));
}

/**
 * Unit tests for EchoClient.
 * @runTestsInSeparateProcesses
 */
class EchoClientTest extends PHPUnit_Framework_TestCase
{
    private $arrMandatoryDefines = array(
        'OAUTH_USER',
        'OAUTH_SECRET',
        'PERSONA_HOST',
        'PERSONA_OAUTH_ROUTE',
        'PERSONA_TOKENCACHE_HOST',
        'PERSONA_TOKENCACHE_PORT',
        'PERSONA_TOKENCACHE_DB'
    );

    /**
     * Ensure that all the required define()'s are set before EchoClient can be used.
     *
     * @dataProvider mandatoryDefines_provider
     */
    function testRequiredDefines($requiredDefineToTest)
    {
        $this->setRequiredDefines($requiredDefineToTest);

        $this->setExpectedException('\Exception', 'Missing define: '.$requiredDefineToTest);
        new \echoclient\EchoClient();
    }

    function testNoEventWrittenWhenNoEchoHostDefined()
    {
        $this->setRequiredDefines();

        $echoClient = new \echoclient\EchoClient();
        $bSent = $echoClient->createEvent('someClass', 'someSource');

        $this->assertFalse($bSent);
    }

    function testEventWritten()
    {
        $this->setRequiredDefines();

        define('ECHO_HOST', 'http://example.com:3002');

        $stubPersonaClient = $this->getMock('\personaclient\PersonaClient', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));

        $response = new \Guzzle\Http\Message\Response('202');

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('send'));
        $stubHttpClient->expects($this->once())->method('send')->will($this->returnValue($response));

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $bSent = $echoClient->createEvent('some.class', 'some-source', array('foo'=>'bar'));

        $this->assertTrue($bSent);
    }

    /* ---------------------------------------------------------------------------------------------------------- */

    /**
     * The dataprovider for testRequiredDefines
     * @see http://phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
     */
    function mandatoryDefines_provider()
    {
        $data = array();

        foreach ($this->arrMandatoryDefines as $defineKey)
        {
            $data[] = array($defineKey);
        }

        return $data;
    }

    /**
     * Set up the mandatory defines, omitting an optional exclusion.
     */
    protected function setRequiredDefines($defineToExclude = null)
    {
        foreach ($this->arrMandatoryDefines as $defineKey)
        {
            if ($defineToExclude === null || $defineKey !== $defineToExclude)
            {
                define($defineKey, $defineKey.'-VALUE');
            }
        }
    }
}
