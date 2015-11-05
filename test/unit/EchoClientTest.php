<?php
if (!defined('APPROOT'))
{
    define('APPROOT', dirname(dirname(__DIR__)));
}

date_default_timezone_set('Europe/London');

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

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));

        $response = new \Guzzle\Http\Message\Response('202');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', array('send'), array('post',''));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('post'));
        $stubHttpClient->expects($this->once())->method('post')->with('http://example.com:3002/1/events')->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $bSent = $echoClient->createEvent('some.class', 'some-source', array('foo'=>'bar'));

        $this->assertTrue($bSent);
    }

    function testRecentEvents()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));

        $expectedEvent = array("class"=>"test.expected.event");
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(array("events"=>array(
            $expectedEvent
        ))));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', array('send'), array('get',''));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('get'));
        $stubHttpClient->expects($this->once())->method('get')->with('http://example.com:3002/1/events?limit=25&class=test.expected.event&key=foo&value=bar')->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getRecentEvents("expected.event","foo","bar");

        $this->assertEquals(array($expectedEvent),$result);
    }

    function testDescribeClassActions()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));
        
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody('["store","count"]');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', array('send'), array('get',''));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('get'));
        $stubHttpClient->expects($this->once())->method('get')->with('http://example.com:3002/1/classes/test.bookmark.createOnlyQuickAdd/actions')->will($this->returnValue($mockRequest));
        
        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->describeClassActions('bookmark.createOnlyQuickAdd');
        
        $this->assertEquals($result, [ "store", "count"]);


        
    }

    function testHitsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));

        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody('{"head":{"type":"hits","class":"test.player.view","group_by":"source","count":27},"results":[{"source":"web.talis-com.b50367b.2014-05-15","hits":45},{"source":"web.talis-com.b692220.2014-05-15","hits":9},{"source":"mobile.android-v1.9","hits":16},{"source":"web.talis-com.f1afa4f.2014-05-13","hits":21},{"source":"mobile.android-v1.7","hits":48},{"source":"web.talis-com.d165ea5.2014-05-01","hits":411},{"source":"web.talis-com.3dceffd.2014-05-15","hits":8},{"source":"mobile.android-v1.6","hits":41},{"source":"web.talis-com.35baf27.2014-04-29","hits":50},{"source":"web.talis-com.13f1318.2014-05-14","hits":18},{"source":"web.talis-com.no-release","hits":219},{"source":"mobile.iOS-v1.97","hits":5},{"source":"web.talis-com-no-release","hits":23},{"source":"web.talis-com.12f4d8c.2014-04-29","hits":29},{"source":"web.talis-com.4a51b66.2014-04-25","hits":56},{"source":"mobile.android-v1.3","hits":4},{"source":"mobile.android-v2.0","hits":39},{"source":"web.talis-com.9df593e.2014-04-17","hits":44},{"source":"web.talis-com.8dac333.2014-04-17","hits":1},{"source":"mobile.iOS-v1.99","hits":60},{"source":"mobile.iOS-v1.98","hits":116},{"source":"web.talis-com.d5e099c.2014-05-15","hits":2},{"source":"mobile.android-v1.8","hits":16},{"source":"web.talis-com.64ade28.2014-04-17","hits":22},{"source":"mobile.iOS-v1.95","hits":10},{"source":"mobile.android-v1.4","hits":1},{"source":"mobile.android-v1.5","hits":20}]}');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', array('send'), array('get',''));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('get'));
        $stubHttpClient->expects($this->once())->method('get')->with('http://example.com:3002/1/analytics/hits?class=test.player.view')->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view');

        $this->assertTrue(isset($result['head']));
        $this->assertTrue(isset($result['results']));
    }

    function testHitsWithOptsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', array(), array(), '', false);
        $stubPersonaClient->expects($this->once())->method('obtainNewToken')->will($this->returnValue(array('access_token'=>'some-token')));

        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody('{"head":{"type":"hits","class":"test.player.view","group_by":"source","count":27},"results":[{"source":"web.talis-com.b50367b.2014-05-15","hits":45},{"source":"web.talis-com.b692220.2014-05-15","hits":9},{"source":"mobile.android-v1.9","hits":16},{"source":"web.talis-com.f1afa4f.2014-05-13","hits":21},{"source":"mobile.android-v1.7","hits":48},{"source":"web.talis-com.d165ea5.2014-05-01","hits":411},{"source":"web.talis-com.3dceffd.2014-05-15","hits":8},{"source":"mobile.android-v1.6","hits":41},{"source":"web.talis-com.35baf27.2014-04-29","hits":50},{"source":"web.talis-com.13f1318.2014-05-14","hits":18},{"source":"web.talis-com.no-release","hits":219},{"source":"mobile.iOS-v1.97","hits":5},{"source":"web.talis-com-no-release","hits":23},{"source":"web.talis-com.12f4d8c.2014-04-29","hits":29},{"source":"web.talis-com.4a51b66.2014-04-25","hits":56},{"source":"mobile.android-v1.3","hits":4},{"source":"mobile.android-v2.0","hits":39},{"source":"web.talis-com.9df593e.2014-04-17","hits":44},{"source":"web.talis-com.8dac333.2014-04-17","hits":1},{"source":"mobile.iOS-v1.99","hits":60},{"source":"mobile.iOS-v1.98","hits":116},{"source":"web.talis-com.d5e099c.2014-05-15","hits":2},{"source":"mobile.android-v1.8","hits":16},{"source":"web.talis-com.64ade28.2014-04-17","hits":22},{"source":"mobile.iOS-v1.95","hits":10},{"source":"mobile.android-v1.4","hits":1},{"source":"mobile.android-v1.5","hits":20}]}');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', array('send'), array('get',''));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', array('get'));
        $stubHttpClient->expects($this->once())->method('get')->with('http://example.com:3002/1/analytics/hits?class=test.player.view&key=some_key&value=some_value')->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getPersonaClient', 'getHttpClient'));
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view',array('key'=>'some_key','value'=>'some_value'));

        $this->assertTrue(isset($result['head']));
        $this->assertTrue(isset($result['results']));
    }

    function testHitsCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getAnalytics'));
        $echoClient->expects($this->once())->method('getAnalytics')->with("some.class","hits");

        $echoClient->getHits("some.class");
    }

    function testAverageCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getAnalytics'));
        $echoClient->expects($this->once())->method('getAnalytics')->with("some.class","average");

        $echoClient->getAverage("some.class");
    }

    function testSumCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getAnalytics'));
        $echoClient->expects($this->once())->method('getAnalytics')->with("some.class","sum");

        $echoClient->getSum("some.class");
    }

    function testMaxCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\echoclient\EchoClient', array('getAnalytics'));
        $echoClient->expects($this->once())->method('getAnalytics')->with("some.class","max");

        $echoClient->getMax("some.class");
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
        define('ECHO_CLASS_PREFIX','test.');
        define('ECHO_HOST', 'http://example.com:3002');
    }
}
