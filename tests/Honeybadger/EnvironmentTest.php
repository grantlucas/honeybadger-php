<?php

namespace Honeybadger;

use \Honeybadger\Util\Arr;

/**
 * Tests Honeybadger\Environment.
 *
 * @group honeybadger
 */
class EnvironmentTest extends TestCase {

	protected $_environment_default = array(
		'_COOKIE' => array(
			'PHPSESSID' => '5jo4beb11n218lr1p0ekdpc916',
			'__utma'    => '1234567890.1234567890.1234567890.1234567890.1234567890.12',
		),
	);

	public function test_factory_should_return_instance_of_environment()
	{
		$this->assertTrue(Environment::factory() instanceof Environment);
	}

	public function test_should_use_standard_server_superglobals_when_not_supplied_data()
	{
		$environment = Environment::factory()->as_array();
		$this->assertNotEmpty($environment);
	}

	public function test_should_remove_non_standard_variables_when_not_supplied_data()
	{
		$this->setEnvironment(array(
			'_SERVER' => array(
				'DATABASE_URL'  => 'postgres://root:p4ssw0rd@localhost/some_db',
				'PASSWORD_SALT' => 'abcdefghijklmnopqrstuvwxyz',
			),
			'_COOKIE' => array(
			),
		));

		$environment = Environment::factory()->as_array();
		$this->assertFalse(isset($environment['DATABASE_URL']));
		$this->assertFalse(isset($environment['PASSWORD_SALT']));
	}

	public function test_should_not_remove_non_standard_variables_matching_http_headers()
	{
		$variables = array(
			'PHP_SELF'             => '/var/www/index.php',
			'argv'                 => array('foo', 'bar', 'baz'),
			'argc'                 => 3,
			'GATEWAY_INTERFACE'    => 'CGI 2.0',
			'SERVER_ADDR'          => '127.0.0.1',
			'SERVER_NAME'          => 'localhost',
			'SERVER_SOFTWARE'      => 'Nginx',
			'SERVER_PROTOCOL'      => 'HTTP/1.1',
			'REQUEST_METHOD'       => 'POST',
			'REQUEST_TIME'         => 123456,
			'REQUEST_TIME_FLOAT'   => 123456.789,
			'QUERY_STRING'         => 'foo=bar&baz[0]=2',
			'DOCUMENT_ROOT'        => '/var/www',
			'HTTPS'                => 'on',
			'REMOTE_ADDR'          => '127.0.0.1',
			'REMOTE_HOST'          => 'localhost',
			'REMOTE_PORT'          => 23415,
			'REMOTE_USER'          => 'admin',
			'REDIRECT_REMOTE_USER' => 'what?',
			'SCRIPT_FILENAME'      => '/var/www/index.php',
			'SERVER_ADMIN'         => 'root',
			'SERVER_PORT'          => 443,
			'SERVER_SIGNATURE'     => 'Nginx v0.8.1-dev',
			'PATH_TRANSLATED'      => 'again, what?',
			'SCRIPT_NAME'          => 'see SCRIPT_FILENAME?',
			'REQUEST_URI'          => '/show/me/something',
			'PHP_AUTH_DIGEST'      => 'asldfhgerlig;asdv',
			'PHP_AUTH_USER'        => 'admin',
			'PHP_AUTH_PW'          => 'test123',
			'AUTH_TYPE'            => 'basic',
			'PATH_INFO'            => '/var/www/index.php/show/me/something',
			'ORIG_PATH_INFO'       => '/',
		);

		$this->setEnvironment(array(
			'_SERVER' => $variables,
			'_COOKIE' => array(
			),
		));

		$environment = Environment::factory()->as_array();

		$this->assertEquals($variables, $environment);
	}

	public function test_should_include_http_headers_when_not_supplied_data()
	{
		$headers = array(
			'HTTP_X_API_KEY'    => '123abc',
			'HTTP_ACCEPT'       => 'application/json',
			'HTTP_CONTENT_TYPE' => 'text/plain; charset=utf-16',
			'HTTP_HOST'         => 'example.com',
			'HTTP_USER_AGENT'   => 'cURL',
		);

		$this->setEnvironment(array(
			'_SERVER' => $headers,
			'_COOKIE' => array(
			),
		));

		$environment = Environment::factory()->as_array();

		$this->assertEquals($headers, $environment);
	}

	public function test_should_include_cookies_when_not_supplied_data()
	{
		$cookies = array(
			'password' => 'smart people put sensitive data in plain text cookies',
		);

		$this->setEnvironment(array(
			'_COOKIE' => $cookies,
		));

		$environment = Environment::factory()->as_array();

		$this->assertEquals($cookies, $environment['rack.request.cookie_hash']);
	}

	public function test_protocol_should_be_http_when_https_blank()
	{
		$this->assertEquals('http', Environment::factory(array())->protocol());
	}

	public function test_protocol_should_be_http_when_https_off()
	{
		$this->assertEquals('http', Environment::factory(array(
			'HTTPS' => 'off',
		))->protocol());
	}

	public function test_protocol_should_be_https_when_https_on()
	{
		$this->assertEquals('https', Environment::factory(array(
			'HTTPS' => 'on',
		))->protocol());
	}

	public function provider_https_on()
	{
		return array(
			array(
				'always',
			),
			array(
				'sometimes',
			),
			array(
				'never',
			),
			array(
				'whenever',
			),
			array(
				'maybe',
			),
			array(
				'mostly',
			),
		);
	}

	/**
	 * @dataProvider provider_https_on
	 */
	public function test_protocol_should_be_https_when_https_not_blank($value)
	{
		$this->assertEquals('https', Environment::factory(array(
			'HTTPS' => $value,
		))->protocol());
	}

	public function test_is_secure()
	{
		$this->assertTrue(Environment::factory(array(
			'HTTPS' => 'on',
		))->is_secure());

		$this->assertFalse(Environment::factory(array(
			'HTTPS' => 'off',
		))->is_secure());
	}

	public function test_host_uses_server_name_when_http_host_unavailable()
	{
		$this->assertEquals('example.com', Environment::factory(array(
			'SERVER_NAME' => 'example.com',
		))->host());
	}

	public function test_host_prefers_http_host()
	{
		$this->assertEquals('foo.net', Environment::factory(array(
			'SERVER_NAME' => 'example.com',
			'HTTP_HOST'   => 'foo.net',
		))->host());
	}

	public function test_port_should_return_server_port()
	{
		$this->assertEquals('123', Environment::factory(array(
			'SERVER_PORT' => '123',
		))->port());
	}

	public function test_port_should_detect_default_when_missing()
	{
		$this->assertEquals(80, Environment::factory(array(
			'HTTPS'       => 'off',
		))->port());

		$this->assertEquals(443, Environment::factory(array(
			'HTTPS' => 'on',
		))->port());
	}

	public function test_non_standard_port_when_ssl()
	{
		$this->assertTrue(Environment::factory(array(
			'HTTPS'       => 'on',
			'SERVER_PORT' => 123,
		))->is_non_standard_port());

		$this->assertFalse(Environment::factory(array(
			'HTTPS'       => 'on',
			'SERVER_PORT' => 443,
		))->is_non_standard_port());
	}

	public function test_non_standard_port_when_http()
	{
		$this->assertTrue(Environment::factory(array(
			'HTTPS'       => 'off',
			'SERVER_PORT' => 456,
		))->is_non_standard_port());

		$this->assertFalse(Environment::factory(array(
			'HTTPS'       => 'off',
			'SERVER_PORT' => 80,
		))->is_non_standard_port());
	}

	public function test_url_uses_environment_when_present()
	{
		$env = Environment::factory(array(
			'url' => 'http://example.com/',
		));

		$this->assertEquals('http://example.com/', $env['url']);
	}

	public function test_url_returns_combined_protocol_host_uri_query_string()
	{
		$env = Environment::factory(array(
			'REQUEST_URI'  => '/foo/bar/xyz?one=1&two=2&three=3',
			'SCRIPT_NAME'  => '/foo/index.php',
			'HTTPS'        => 'on',
			'HTTP_HOST'    => 'www.example.com',
			'QUERY_STRING' => 'one=1&two=2&three=3',
		));

		$this->assertEquals('https://www.example.com/foo/bar/xyz?one=1&two=2&three=3', $env['url']);
	}

	public function test_url_adds_port_when_non_standard()
	{
		$env = Environment::factory(array(
			'REQUEST_URI'  => '/foo/bar/xyz?one=1&two=2&three=3',
			'SCRIPT_NAME'  => '/foo/index.php',
			'HTTPS'        => '',
			'HTTP_HOST'    => 'www.example.com',
			'QUERY_STRING' => 'one=1&two=2&three=3',
			'SERVER_PORT'  => '123',
		));

		$this->assertEquals('http://www.example.com:123/foo/bar/xyz?one=1&two=2&three=3', $env['url']);
	}

	public function test_url_returns_null_when_empty_host_and_path()
	{
		$env = Environment::factory(array(
		));

		$this->assertNull($env->url);
	}

}