<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Test for the client class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\api;

use advanced_testcase;
use cache;
use curl;
use moodle_exception;
use ReflectionClass;
use ReflectionException;

/**
 * Unit tests for mod_edflex\classes\api\client
 *
 * @covers \mod_edflex\api\client
 */
final class client_test extends advanced_testcase {
    /**
     * Get a cache instance with a valid access_token
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    private function get_cache_with_a_valid_token() {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();
        $validtoken = [
            'access_token' => 'cached_token',
            'expires_in' => 3600,
            'expire_ts' => time() + 3600,
        ];
        $cache->set('access_token', $validtoken);

        return $cache;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Helper to call the private method via reflection.
     *
     * @param client $client The client instance.
     * @param string $method The method name.
     *
     * @throws ReflectionException
     */
    private function invoke_client_method(client $client, string $method): void {
        $ref = new ReflectionClass($client);
        $method = $ref->getMethod($method);
        $method->setAccessible(true);
        $method->invoke($client);
    }

    /**
     * Tests the deletion of an access token from the cache if it is expired.
     *
     * @dataProvider provide_test_delete_access_token_from_cache_if_expired_data
     *
     * @param array $token The access token.
     * @param bool $expired True if the access token is expired, false otherwise.
     *
     * @throws ReflectionException
     */
    public function test_delete_access_token_from_cache_if_expired(array $token, bool $expired): void {
        $cache  = cache::make('mod_edflex', 'api');
        $cache->purge();

        $client = new client('ci', 'cs', 'https://e.test', new curl(), $cache);

        $cache->set('access_token', $token);

        $this->invoke_client_method($client, 'delete_access_token_from_cache_if_expired');

        $tokenfromcache = $cache->get('access_token');

        $this->assertSame(empty($tokenfromcache), $expired);
    }

    /**
     * Test successful access token request
     */
    public function test_request_access_token_success(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $expectedresponse = [
            'access_token' => 'test_token_123',
            'expires_in' => 3600,
        ];

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->with(
                'https://e.test/connect/v1/auth/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'ci',
                    'client_secret' => 'cs',
                ]
            )
            ->willReturn(json_encode($expectedresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $response = $client->request_access_token();

        $this->assertArrayHasKey('access_token', $response);
        $this->assertArrayHasKey('expires_in', $response);
        $this->assertArrayHasKey('expire_ts', $response);
        $this->assertEquals('test_token_123', $response['access_token']);
        $this->assertEquals(3600, $response['expires_in']);
        $this->assertEquals(time() + 3600 - 30, $response['expire_ts']);

        $cachedtoken = $cache->get('access_token');
        $this->assertEquals($response, $cachedtoken);
    }

    /**
     * Test access token request with an invalid response
     */
    public function test_request_access_token_invalid_response(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $errorresponse = [
            'error' => [
                'message' => 'Invalid credentials',
                'code' => 401,
            ],
        ];

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($errorresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);

        $client->request_access_token();

        $this->assertFalse($cache->get('access_token'));
    }

    /**
     * Test access token request with malformed response
     */
    public function test_request_access_token_malformed_response(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $malformedresponse = [
            'some_field' => 'some_value',
        ];

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($malformedresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->assertFalse($cache->get('access_token'));
        $this->expectException(moodle_exception::class);
        $client->request_access_token();
    }

    /**
     * Test constructor with valid parameters
     */
    public function test_constructor_valid_parameters(): void {
        new client(
            'valid_id',
            'valid_secret',
            'https://e.test',
            $this->getMockBuilder(curl::class)->getMock(),
            $this->get_cache_with_a_valid_token()
        );

        $this->assertTrue(true);
    }

    /**
     * Tests the constructor of the client class with invalid parameters.
     *
     * @param string $clientid The client ID.
     * @param string $clientsecret The client secret.
     * @param string $apiurl The API URL.
     *
     * @dataProvider provide_constructor_invalid_parameters_data
     */
    public function test_constructor_invalid_parameters(
        string $clientid,
        string $clientsecret,
        string $apiurl
    ): void {
        $this->expectException(moodle_exception::class);

        new client(
            $clientid,
            $clientsecret,
            $apiurl,
            $this->getMockBuilder(curl::class)->getMock(),
            cache::make('mod_edflex', 'api')
        );
    }

    /**
     * Test from_array static method
     *
     * @throws ReflectionException
     */
    public function test_from_array(): void {
        $credentials = [
            'clientid' => 'test_id',
            'clientsecret' => 'test_secret',
            'apiurl' => 'https://e.test',
        ];

        $client = client::from_array($credentials);

        $ref = new ReflectionClass($client);

        $clientidprop = $ref->getProperty('clientid');
        $clientidprop->setAccessible(true);
        $this->assertEquals('test_id', $clientidprop->getValue($client));

        $clientsecretprop = $ref->getProperty('clientsecret');
        $clientsecretprop->setAccessible(true);
        $this->assertEquals('test_secret', $clientsecretprop->getValue($client));

        $apiurlprop = $ref->getProperty('apiurl');
        $apiurlprop->setAccessible(true);
        $this->assertEquals('https://e.test', $apiurlprop->getValue($client));
    }

    /**
     * Test from_config static method
     *
     * @throws ReflectionException
     */
    public function test_from_config(): void {
        set_config('clientid', 'config_id', 'mod_edflex');
        set_config('clientsecret', 'config_secret', 'mod_edflex');
        set_config('apiurl', 'https://e.test', 'mod_edflex');

        $client = client::from_config();

        $ref = new ReflectionClass($client);

        $clientidprop = $ref->getProperty('clientid');
        $clientidprop->setAccessible(true);
        $this->assertEquals('config_id', $clientidprop->getValue($client));

        $clientsecretprop = $ref->getProperty('clientsecret');
        $clientsecretprop->setAccessible(true);
        $this->assertEquals('config_secret', $clientsecretprop->getValue($client));

        $apiurlprop = $ref->getProperty('apiurl');
        $apiurlprop->setAccessible(true);
        $this->assertEquals('https://e.test', $apiurlprop->getValue($client));
    }

    /**
     * Test get_access_token with cached token
     */
    public function test_get_access_token_with_cached_token(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->never())
            ->method('post');

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $token = $client->get_access_token();

        $this->assertEquals('cached_token', $token);
    }

    /**
     * Test get_access_token with an expired token
     */
    public function test_get_access_token_with_expired_token(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $expiredtoken = [
            'access_token' => 'expired_token',
            'expires_in' => 3600,
            'expire_ts' => time() - 10,
        ];
        $cache->set('access_token', $expiredtoken);

        $newtoken = [
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ];

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($newtoken));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $token = $client->get_access_token();

        $this->assertEquals('new_token', $token);
    }

    /**
     * Test get_access_token with no cached token
     */
    public function test_get_access_token_with_no_cached_token(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $newtoken = [
            'access_token' => 'fresh_token',
            'expires_in' => 3600,
        ];

        $curl = $this->getMockBuilder(curl::class)
            ->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($newtoken));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $token = $client->get_access_token();

        $this->assertEquals('fresh_token', $token);
    }

    /**
     * Test parse_response method with successful JSON responses
     *
     * @param string $rawresponse The raw response.
     * @param array $responseinfo The response info.
     * @param bool $expectedjson True if the response is expected to be JSON, false otherwise.
     * @param array $expected The expected result.
     *
     * @dataProvider provide_parse_response_success_json_data
     */
    public function test_parse_response_success_json(
        string $rawresponse,
        array $responseinfo,
        bool $expectedjson,
        array $expected
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $result = $client->parse_response($rawresponse, $responseinfo, $expectedjson);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test parse_response method with successful non-JSON responses
     */
    public function test_parse_response_success_non_json(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $rawresponse = '<xml>Some XML content</xml>';
        $responseinfo = ['http_code' => 200];

        $result = $client->parse_response($rawresponse, $responseinfo, false);

        $this->assertEquals($rawresponse, $result);
    }

    /**
     * Test parse_response method with error responses
     *
     * @param string $rawresponse The raw response.
     * @param array $responseinfo The response info.
     * @param bool $expectedjson True if the response is expected to be JSON, false otherwise.
     * @param string $expectedexceptionmessage The expected exception message.
     *
     * @dataProvider provide_parse_response_error_data
     */
    public function test_parse_response_errors(
        string $rawresponse,
        array $responseinfo,
        bool $expectedjson,
        string $expectedexceptionmessage
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches($expectedexceptionmessage);

        $client->parse_response($rawresponse, $responseinfo, $expectedjson);
    }

    /**
     * Test parse_response with malformed JSON in success status code
     */
    public function test_parse_response_malformed_json_success_status(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $rawresponse = '{invalid json';
        $responseinfo = ['http_code' => 200];

        $this->expectException(moodle_exception::class);

        $client->parse_response($rawresponse, $responseinfo);
    }

    /**
     * Test get_contents method with various filters
     *
     * @param array $filters The filters.
     * @param array $expectedparams The expected parameters.
     * @param array $apiresponse The API response.
     *
     * @dataProvider provide_get_contents_filters_data
     */
    public function test_get_contents_with_filters(
        array $filters,
        array $expectedparams,
        array $apiresponse
    ): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer cached_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with(
                'https://e.test/connect/v1/resources',
                $expectedparams
            )
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_contents($filters);

        $this->assertEquals($apiresponse, $result);
    }

    /**
     * Test get_contents with pagination parameters
     */
    public function test_get_contents_with_pagination(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                ['id' => 'content1', 'title' => 'Content 1'],
                ['id' => 'content2', 'title' => 'Content 2'],
            ],
            'meta' => ['total' => 100, 'page' => 2],
        ];

        $expectedparams = [
            'page' => ['number' => 2, 'size' => 10],
            'filter' => [],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->with(
                'https://e.test/connect/v1/resources',
                $expectedparams
            )
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_contents([], 2, 10);

        $this->assertEquals($apiresponse, $result);
    }

    /**
     * Test get_contents with contentIds filter
     *
     * @param array $contentids The content IDs.
     * @param string $expectedcontentids The expected content IDs.
     * @param bool $shouldthrow True if the method should throw an exception, false otherwise.
     *
     * @dataProvider provide_get_contents_contentids_data
     */
    public function test_get_contents_with_contentids(
        $contentids,
        string $expectedcontentids,
        bool $shouldthrow = false
    ): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        if ($shouldthrow) {
            $this->expectException(moodle_exception::class);
            $this->expectExceptionMessage('Invalid content ID');
        } else {
            $expectedparams = [
                'page' => ['number' => 1, 'size' => 50],
                'filter' => ['contentIds' => $expectedcontentids],
            ];

            $apiresponse = ['data' => []];

            $curl->expects($this->once())
                ->method('get')
                ->with(
                    'https://e.test/connect/v1/resources',
                    $expectedparams
                )
                ->willReturn(json_encode($apiresponse));

            $curl->expects($this->once())
                ->method('get_info')
                ->willReturn(['http_code' => 200]);
        }

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_contents(['contentIds' => $contentids]);

        if (!$shouldthrow) {
            $this->assertEquals($apiresponse, $result);
        }
    }

    /**
     * Test get_contents with API error response
     */
    public function test_get_contents_with_api_error(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $errorresponse = [
            'errors' => [
                ['code' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid filter'],
            ],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($errorresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 400]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches('/400 Bad Request Invalid filter/');

        $client->get_contents(['query' => 'test']);
    }

    /**
     * Test get_contents with empty filters
     */
    public function test_get_contents_with_empty_filters(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                ['id' => 'content1', 'title' => 'Content 1'],
            ],
        ];

        $expectedparams = [
            'page' => ['number' => 1, 'size' => 50],
            'filter' => [],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->with(
                'https://e.test/connect/v1/resources',
                $expectedparams
            )
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_contents([]);

        $this->assertEquals($apiresponse, $result);
    }

    /**
     * Test build_error_message_from_response method
     *
     * @param array $errors The errors.
     * @param string $expectedmessage The expected message.
     *
     * @dataProvider provide_build_error_message_from_response_data
     */
    public function test_build_error_message_from_response(
        array $errors,
        string $expectedmessage
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $result = $client->build_error_message_from_response($errors);

        $this->assertEquals($expectedmessage, $result);
    }

    /**
     * Test build_error_message_from_response with empty errors array
     */
    public function test_build_error_message_from_response_empty_array(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $result = $client->build_error_message_from_response([]);

        $this->assertEquals('', $result);
    }

    /**
     * Test build_error_message_from_response with missing fields
     *
     * @param array $errors The errors.
     * @param string $expectedmessage The expected message.
     *
     * @dataProvider provide_build_error_message_missing_fields_data
     */
    public function test_build_error_message_from_response_missing_fields(
        array $errors,
        string $expectedmessage
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $result = $client->build_error_message_from_response($errors);

        $this->assertEquals($expectedmessage, $result);
    }

    /**
     * Test build_error_message_from_response with special characters
     */
    public function test_build_error_message_from_response_special_chars(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();
        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $errors = [
            [
                'code' => '400',
                'title' => 'Error with "quotes"',
                'detail' => 'Detail with special chars: <>&',
            ],
        ];

        $result = $client->build_error_message_from_response($errors);

        $this->assertEquals('400 Error with "quotes" Detail with special chars: <>&', $result);
    }

    /**
     * Test get_contents_by_ids with a small array of IDs
     */
    public function test_get_contents_by_ids_small_array(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $contentids = ['id1', 'id2', 'id3'];

        $apiresponse = [
            'data' => [
                ['id' => 'id1', 'title' => 'Content 1'],
                ['id' => 'id2', 'title' => 'Content 2'],
                ['id' => 'id3', 'title' => 'Content 3'],
            ],
        ];

        $expectedparams = [
            'page' => ['number' => 1, 'size' => 50],
            'filter' => ['contentIds' => 'id1,id2,id3'],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->with(
                'https://e.test/connect/v1/resources',
                $expectedparams
            )
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $generator = $client->get_contents_by_ids($contentids);

        $results = [];
        foreach ($generator as $id => $content) {
            $results[$id] = $content;
        }

        $this->assertCount(3, $results);
        $this->assertEquals('Content 1', $results['id1']['title']);
        $this->assertEquals('Content 2', $results['id2']['title']);
        $this->assertEquals('Content 3', $results['id3']['title']);
    }

    /**
     * Test get_contents_by_ids with chunking (more than 50 IDs)
     */
    public function test_get_contents_by_ids_with_chunking(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $contentids = [];
        for ($i = 1; $i <= 75; $i++) {
            $contentids[] = 'id' . $i;
        }

        $firstchunkresponse = ['data' => []];
        for ($i = 1; $i <= 50; $i++) {
            $firstchunkresponse['data'][] = ['id' => 'id' . $i, 'title' => 'Content ' . $i];
        }

        $secondchunkresponse = ['data' => []];
        for ($i = 51; $i <= 75; $i++) {
            $secondchunkresponse['data'][] = ['id' => 'id' . $i, 'title' => 'Content ' . $i];
        }

        $curl->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                json_encode($firstchunkresponse),
                json_encode($secondchunkresponse)
            );

        $curl->expects($this->exactly(2))
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $generator = $client->get_contents_by_ids($contentids);

        $results = [];

        foreach ($generator as $id => $content) {
            $results[$id] = $content;
        }

        $this->assertCount(75, $results);
        $this->assertEquals('Content 1', $results['id1']['title']);
        $this->assertEquals('Content 50', $results['id50']['title']);
        $this->assertEquals('Content 51', $results['id51']['title']);
        $this->assertEquals('Content 75', $results['id75']['title']);
    }

    /**
     * Test get_contents_by_ids with an empty array
     */
    public function test_get_contents_by_ids_empty_array(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->never())
            ->method('get');

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $generator = $client->get_contents_by_ids([]);

        $results = [];
        foreach ($generator as $id => $content) {
            $results[$id] = $content;
        }

        $this->assertCount(0, $results);
    }

    /**
     * Test get_contents_by_ids with exactly 50 IDs (boundary case)
     */
    public function test_get_contents_by_ids_exactly_fifty(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $contentids = [];
        for ($i = 1; $i <= 50; $i++) {
            $contentids[] = 'id' . $i;
        }

        $apiresponse = ['data' => []];
        for ($i = 1; $i <= 50; $i++) {
            $apiresponse['data'][] = ['id' => 'id' . $i, 'title' => 'Content ' . $i];
        }

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $generator = $client->get_contents_by_ids($contentids);

        $results = [];

        foreach ($generator as $id => $content) {
            $results[$id] = $content;
        }

        $this->assertCount(50, $results);
    }

    /**
     * Test get_contents_by_ids with missing content in response
     */
    public function test_get_contents_by_ids_missing_content(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $contentids = ['id1', 'id2', 'id3', 'id4'];

        $apiresponse = [
            'data' => [
                ['id' => 'id1', 'title' => 'Content 1'],
                ['id' => 'id3', 'title' => 'Content 3'],
            ],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $generator = $client->get_contents_by_ids($contentids);

        $results = [];

        foreach ($generator as $id => $content) {
            $results[$id] = $content;
        }

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('id1', $results);
        $this->assertArrayHasKey('id3', $results);
        $this->assertArrayNotHasKey('id2', $results);
        $this->assertArrayNotHasKey('id4', $results);
    }

    /**
     * Test get_contents_by_ids with API error
     */
    public function test_get_contents_by_ids_api_error(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $contentids = ['id1', 'id2'];

        $errorresponse = [
            'errors' => [
                ['code' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid content IDs'],
            ],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($errorresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 400]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches('/400 Bad Request Invalid content IDs/');

        $generator = $client->get_contents_by_ids($contentids);

        iterator_to_array($generator);
    }

    /**
     * Test get_scorm with valid URLs
     *
     * @param string $scormurl The SCORM URL.
     * @param string $expectedresponse The expected response.
     *
     * @dataProvider provide_get_scorm_valid_urls_data
     */
    public function test_get_scorm_valid_urls(
        string $scormurl,
        string $expectedresponse
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer cached_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with($scormurl)
            ->willReturn($expectedresponse);

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_scorm($scormurl);

        $this->assertEquals($expectedresponse, $result);
    }

    /**
     * Test get_scorm with invalid URLs
     *
     * @param string $scormurl The SCORM URL.
     *
     * @dataProvider provide_get_scorm_invalid_urls_data
     */
    public function test_get_scorm_invalid_urls(string $scormurl): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->never())
            ->method('get');

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid SCORM URL provided');

        $client->get_scorm($scormurl);
    }

    /**
     * Test get_scorm with API error responses
     *
     * @param int $httpcode The HTTP code.
     * @param string $errorresponse The error response.
     * @param string $expectedexception The expected exception.
     *
     * @dataProvider provide_get_scorm_error_responses_data
     */
    public function test_get_scorm_error_responses(
        int $httpcode,
        string $errorresponse,
        string $expectedexception
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('get')
            ->willReturn($errorresponse);

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => $httpcode]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches($expectedexception);

        $client->get_scorm('https://example.com/scorm/package.zip');
    }

    /**
     * Test get_scorm with binary content
     */
    public function test_get_scorm_binary_content(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $curl = $this->getMockBuilder(curl::class)->getMock();

        $binarycontent = "PK\x03\x04\x14\x00\x00\x00\x08\x00" . str_repeat("\x00", 100);

        $curl->expects($this->once())
            ->method('get')
            ->willReturn($binarycontent);

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_scorm('https://example.com/scorm/package.zip');

        $this->assertEquals($binarycontent, $result);
    }

    /**
     * Test get_scorm with expired token (triggers token refresh)
     */
    public function test_get_scorm_with_expired_token(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $expiredtoken = [
            'access_token' => 'expired_token',
            'expires_in' => 3600,
            'expire_ts' => time() - 10,
        ];
        $cache->set('access_token', $expiredtoken);

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $newtoken = ['access_token' => 'new_token', 'expires_in' => 3600];

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($newtoken));

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer new_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with('https://example.com/scorm/package.zip')
            ->willReturn('SCORM content');

        $curl->expects($this->exactly(2))
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_scorm('https://example.com/scorm/package.zip');

        $this->assertEquals('SCORM content', $result);
    }

    /**
     * Test can_connect_to_the_api with successful connection
     */
    public function test_can_connect_to_the_api_success(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $successresponse = [
            'access_token' => 'valid_token_123',
            'expires_in' => 3600,
        ];

        $curl->expects($this->once())
            ->method('post')
            ->with(
                'https://e.test/connect/v1/auth/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'ci',
                    'client_secret' => 'cs',
                ]
            )
            ->willReturn(json_encode($successresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertTrue($result);
    }

    /**
     * Test can_connect_to_the_api with failed connection
     *
     * @param string $response The response.
     * @param int $httpcode The HTTP code.
     *
     * @dataProvider provide_can_connect_to_the_api_failure_data
     */
    public function test_can_connect_to_the_api_failure(
        string $response,
        int $httpcode
    ): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => $httpcode]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertFalse($result);
    }

    /**
     * Test can_connect_to_the_api with network error
     */
    public function test_can_connect_to_the_api_network_error(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willThrowException(new \Exception('Network error'));

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertFalse($result);
    }

    /**
     * Test can_connect_to_the_api with malformed JSON response
     */
    public function test_can_connect_to_the_api_malformed_json(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('post')
            ->willReturn('{invalid json');

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertFalse($result);
    }

    /**
     * Test can_connect_to_the_api with missing access_token in response
     */
    public function test_can_connect_to_the_api_missing_token(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $responsewithoutttoken = [
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($responsewithoutttoken));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertFalse($result);
    }

    /**
     * Test can_connect_to_the_api with empty access_token
     */
    public function test_can_connect_to_the_api_empty_token(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $responsewithemptytoken = [
            'access_token' => '',
            'expires_in' => 3600,
        ];

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($responsewithemptytoken));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertFalse($result);
    }

    /**
     * Test can_connect_to_the_api does not cache the token
     */
    public function test_can_connect_to_the_api_no_caching(): void {
        $cache = cache::make('mod_edflex', 'api');
        $cache->purge();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $successresponse = [
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ];

        $curl->expects($this->once())
            ->method('post')
            ->willReturn(json_encode($successresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->can_connect_to_the_api();

        $this->assertTrue($result);

        $cachedtoken = $cache->get('access_token');
        $this->assertNotEmpty($cachedtoken);
        $this->assertEquals('test_token', $cachedtoken['access_token']);
    }

    /**
     * Test get_catalogs without cache (TTL = 0)
     */
    public function test_get_catalogs_without_cache(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                ['id' => 'catalog1', 'name' => 'Catalog 1'],
                ['id' => 'catalog2', 'name' => 'Catalog 2'],
            ],
        ];

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer cached_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with('https://e.test/connect/v1/catalogs')
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_catalogs(0);

        $this->assertEquals($apiresponse, $result);

        $cachedcatalogs = $cache->get('catalogs');
        $this->assertFalse($cachedcatalogs);
    }

    /**
     * Test get_catalogs with caching (default TTL)
     */
    public function test_get_catalogs_with_caching(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $cache->delete('catalogs');

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                ['id' => 'catalog1', 'name' => 'Catalog 1'],
                ['id' => 'catalog2', 'name' => 'Catalog 2'],
            ],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $result = $client->get_catalogs(3600);
        $this->assertEquals($apiresponse, $result);

        $result2 = $client->get_catalogs(3600);
        $this->assertEquals($apiresponse, $result2);
    }

    /**
     * Test get_catalogs with expired cache
     */
    public function test_get_catalogs_with_expired_cache(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $oldcatalogs = [
            'data' => [['id' => 'old_catalog', 'name' => 'Old Catalog']],
        ];
        $expiredtime = time() - 100;
        $cache->set('catalogs', [$oldcatalogs, $expiredtime]);

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $newcatalogs = [
            'data' => [
                ['id' => 'new_catalog1', 'name' => 'New Catalog 1'],
                ['id' => 'new_catalog2', 'name' => 'New Catalog 2'],
            ],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($newcatalogs));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_catalogs(3600);

        $this->assertEquals($newcatalogs, $result);
        $this->assertNotEquals($oldcatalogs, $result);
    }

    /**
     * Test get_catalogs with valid cache
     */
    public function test_get_catalogs_with_valid_cache(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $cachedcatalogs = [
            'data' => [
                ['id' => 'cached1', 'name' => 'Cached Catalog 1'],
                ['id' => 'cached2', 'name' => 'Cached Catalog 2'],
            ],
        ];
        $futuretime = time() + 1800;
        $cache->set('catalogs', [$cachedcatalogs, $futuretime]);

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->never())
            ->method('get');

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_catalogs(3600);

        $this->assertEquals($cachedcatalogs, $result);
    }

    /**
     * Test get_catalogs with API error
     *
     * @param int $httpcode The HTTP code.
     * @param string $errorresponse The error response.
     * @param string $expectedexception The expected exception.
     *
     * @dataProvider provide_get_catalogs_error_data
     */
    public function test_get_catalogs_with_api_error(
        int $httpcode,
        string $errorresponse,
        string $expectedexception
    ): void {
        $cache = $this->get_cache_with_a_valid_token();
        $cache->delete('catalogs');

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $curl->expects($this->once())
            ->method('get')
            ->willReturn($errorresponse);

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => $httpcode]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches($expectedexception);

        $client->get_catalogs();
    }

    /**
     * Test get_catalogs with empty response
     */
    public function test_get_catalogs_empty_response(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $cache->delete('catalogs');

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $emptyresponse = ['data' => []];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($emptyresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_catalogs();

        $this->assertEquals($emptyresponse, $result);
        $this->assertEmpty($result['data']);
    }

    /**
     * Test get_catalogs with custom TTL
     */
    public function test_get_catalogs_with_custom_ttl(): void {
        $cache = $this->get_cache_with_a_valid_token();
        $cache->delete('catalogs');

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [['id' => 'catalog1', 'name' => 'Catalog 1']],
        ];

        $curl->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);

        $customttl = 7200;
        $result = $client->get_catalogs($customttl);

        $this->assertEquals($apiresponse, $result);

        [$cacheddata, $expirets] = $cache->get('catalogs');
        $this->assertEquals($apiresponse, $cacheddata);
        $this->assertGreaterThan(time() + 7190, $expirets);
        $this->assertLessThan(time() + 7210, $expirets);
    }

    /**
     * Provides a dataset to test deletion of access tokens from cache if they are expired.
     *
     * @return array An array where each element contains an access token with its expiration timestamp
     *               and a boolean indicating whether the token should be deleted from the cache.
     */
    public static function provide_test_delete_access_token_from_cache_if_expired_data(): array {
        return [
            [['access_token' => 'expiredtoken1', 'expire_ts' => time() - 60], true],
            [['access_token' => 'expiredtoken2', 'expire_ts' => null], true],
            [['access_token' => 'expiredtoken2'], true],
            [['access_token' => 'validtoken', 'expire_ts' => time() + 60], false],
        ];
    }

    /**
     * Provides a dataset to test constructor validation of required parameters.
     *
     * @return array An array where each element contains parameters for the constructor
     *               and the expected exception message.
     */
    public static function provide_constructor_invalid_parameters_data(): array {
        return [
            ['', 'valid_secret', 'https://e.test'],
            ['valid_id', '', 'https://e.test'],
            ['valid_id', 'valid_secret', ''],
        ];
    }


    /**
     * Data provider for successful parse_response JSON tests
     */
    public static function provide_parse_response_success_json_data(): array {
        return [
            'simple_json_object' => [
                'rawresponse' => '{"key": "value"}',
                'responseinfo' => ['http_code' => 200],
                'expectedjson' => true,
                'expected' => ['key' => 'value'],
            ],
            'complex_json_object' => [
                'rawresponse' => '{"data": {"id": 123, "name": "Test"}, "meta": {"count": 1}}',
                'responseinfo' => ['http_code' => 200],
                'expectedjson' => true,
                'expected' => ['data' => ['id' => 123, 'name' => 'Test'], 'meta' => ['count' => 1]],
            ],
            'empty_json_object' => [
                'rawresponse' => '{}',
                'responseinfo' => ['http_code' => 200],
                'expectedjson' => true,
                'expected' => [],
            ],
        ];
    }

    /**
     * Data provider for parse_response error tests
     */
    public static function provide_parse_response_error_data(): array {
        return [
            'error_400_with_errors_array' => [
                'rawresponse' => '{"errors": [{"code": "400", "title": "Bad Request", "detail": "Invalid parameter"}]}',
                'responseinfo' => ['http_code' => 400],
                'expectedjson' => true,
                'expectedexceptionmessage' => '/400 Bad Request Invalid parameter/',
            ],
            'error_200_with_errors_array' => [
                'rawresponse' => '{"errors": [{"code": "200", "title": "Bad Request", "detail": "Invalid parameter"}]}',
                'responseinfo' => ['http_code' => 200],
                'expectedjson' => true,
                'expectedexceptionmessage' => '/200 Bad Request Invalid parameter/',
            ],
            'multiple_errors' => [
                'rawresponse' => '{"errors": [{"code": "400", "title": "Error 1", "detail": "Detail 1"}, ' .
                    '{"code": "400", "title": "Error 2", "detail": "Detail 2"}]}',
                'responseinfo' => ['http_code' => 400],
                'expectedjson' => true,
                'expectedexceptionmessage' => '/400 Error 1 Detail 1. 400 Error 2 Detail 2/',
            ],
            'error_without_errors_field' => [
                'rawresponse' => '{"message": "Something went wrong"}',
                'responseinfo' => ['http_code' => 400],
                'expectedjson' => true,
                'expectedexceptionmessage' => '/unknown error/',
            ],
        ];
    }

    /**
     * Data provider for get_contents filter tests
     */
    public static function provide_get_contents_filters_data(): array {
        return [
            'single_filter_query' => [
                'filters' => ['query' => 'search term'],
                'expectedparams' => [
                    'page' => ['number' => 1, 'size' => 50],
                    'filter' => ['query' => 'search term'],
                ],
                'apiresponse' => [
                    'data' => [['id' => 'c1', 'title' => 'Result 1']],
                ],
            ],
            'multiple_filters' => [
                'filters' => [
                    'query' => 'test',
                    'type' => 'video',
                    'language' => 'en',
                    'category' => 'cat123',
                ],
                'expectedparams' => [
                    'page' => ['number' => 1, 'size' => 50],
                    'filter' => [
                        'query' => 'test',
                        'type' => 'video',
                        'language' => 'en',
                        'categoryId' => 'cat123',
                    ],
                ],
                'apiresponse' => [
                    'data' => [
                        ['id' => 'v1', 'title' => 'Video 1', 'type' => 'video'],
                        ['id' => 'v2', 'title' => 'Video 2', 'type' => 'video'],
                    ],
                ],
            ],
            'filter_with_special_chars' => [
                'filters' => ['query' => '<script>alert("xss")</script>'],
                'expectedparams' => [
                    'page' => ['number' => 1, 'size' => 50],
                    'filter' => ['query' => 'alert("xss")'],
                ],
                'apiresponse' => ['data' => []],
            ],
        ];
    }

    /**
     * Test get_categories with default pagination and no filters.
     */
    public function test_get_categories_success_default_params(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                [
                    'id' => 'cat-001',
                    'attributes' => [
                        'title' => ['default' => 'Category 1'],
                        'position' => 1,
                    ],
                ],
                [
                    'id' => 'cat-002',
                    'attributes' => [
                        'title' => ['default' => 'Category 2'],
                        'position' => 2,
                    ],
                ],
            ],
            'links' => ['next' => false],
        ];

        $expectedparams = [
            'page' => ['number' => 1, 'size' => 50],
            'filter' => [],
        ];

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer cached_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with('https://e.test/connect/v1/catalogs/catalog-123/categories', $expectedparams)
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_categories('catalog-123');

        $this->assertEquals($apiresponse, $result);
    }

    /**
     * Test get_categories with nestingLevel filter and custom pagination.
     */
    public function test_get_categories_with_filters_and_pagination(): void {
        $cache = $this->get_cache_with_a_valid_token();

        $curl = $this->getMockBuilder(curl::class)->getMock();

        $apiresponse = [
            'data' => [
                [
                    'id' => 'cat-010',
                    'attributes' => [
                        'title' => ['default' => 'Level 1 Category'],
                        'position' => 10,
                    ],
                ],
            ],
            'links' => ['next' => true],
        ];

        $expectedparams = [
            'page' => ['number' => 2, 'size' => 25],
            'filter' => ['nestingLevel' => 1],
        ];

        $curl->expects($this->once())
            ->method('setHeader')
            ->with([
                'Authorization: Bearer cached_token',
                'Content-Type: application/json',
            ]);

        $curl->expects($this->once())
            ->method('get')
            ->with('https://e.test/connect/v1/catalogs/catalog-abc/categories', $expectedparams)
            ->willReturn(json_encode($apiresponse));

        $curl->expects($this->once())
            ->method('get_info')
            ->willReturn(['http_code' => 200]);

        $client = new client('ci', 'cs', 'https://e.test', $curl, $cache);
        $result = $client->get_categories('catalog-abc', ['nestingLevel' => 1], 2, 25);

        $this->assertEquals($apiresponse, $result);
    }

    /**
     * Data provider for get_contents contentIds tests
     */
    public static function provide_get_contents_contentids_data(): array {
        return [
            'contentids_as_array' => [
                'contentids' => ['id1', 'id2', 'id3'],
                'expectedcontentids' => 'id1,id2,id3',
                'shouldthrow' => false,
            ],
            'contentids_as_string' => [
                'contentids' => 'id1,id2,id3',
                'expectedcontentids' => 'id1,id2,id3',
                'shouldthrow' => false,
            ],
            'contentids_non_alphanumeric' => [
                'contentids' => '^>',
                'expectedcontentids' => '',
                'shouldthrow' => true,
            ],
        ];
    }

    /**
     * Data provider for build_error_message_from_response tests
     */
    public static function provide_build_error_message_from_response_data(): array {
        return [
            'single_error_complete' => [
                'errors' => [
                    [
                        'code' => '404',
                        'title' => 'Not Found',
                        'detail' => 'Resource not found',
                    ],
                ],
                'expectedmessage' => '404 Not Found Resource not found',
            ],
            'multiple_errors' => [
                'errors' => [
                    [
                        'code' => '400',
                        'title' => 'Bad Request',
                        'detail' => 'Invalid parameter',
                    ],
                    [
                        'code' => '422',
                        'title' => 'Unprocessable Entity',
                        'detail' => 'Validation failed',
                    ],
                ],
                'expectedmessage' => '400 Bad Request Invalid parameter. 422 Unprocessable Entity Validation failed',
            ],
            'three_errors' => [
                'errors' => [
                    [
                        'code' => '401',
                        'title' => 'Unauthorized',
                        'detail' => 'Invalid token',
                    ],
                    [
                        'code' => '403',
                        'title' => 'Forbidden',
                        'detail' => 'Access denied',
                    ],
                    [
                        'code' => '500',
                        'title' => 'Internal Server Error',
                        'detail' => 'Server error',
                    ],
                ],
                'expectedmessage' => '401 Unauthorized Invalid token. ' .
                    '403 Forbidden Access denied. 500 Internal Server Error Server error',
            ],
        ];
    }

    /**
     * Data provider for build_error_message_from_response with missing fields
     */
    public static function provide_build_error_message_missing_fields_data(): array {
        return [
            'missing_code' => [
                'errors' => [
                    [
                        'title' => 'Error Title',
                        'detail' => 'Error Detail',
                    ],
                ],
                'expectedmessage' => ' Error Title Error Detail',
            ],
            'missing_title' => [
                'errors' => [
                    [
                        'code' => '400',
                        'detail' => 'Error Detail',
                    ],
                ],
                'expectedmessage' => '400  Error Detail',
            ],
            'only_code' => [
                'errors' => [
                    [
                        'code' => '500',
                    ],
                ],
                'expectedmessage' => '500  ',
            ],
        ];
    }

    /**
     * Data provider for valid SCORM URLs
     */
    public static function provide_get_scorm_valid_urls_data(): array {
        return [
            'standard_scorm_url' => [
                'scormurl' => 'https://example.com/scorm/package.zip',
                'expectedresponse' => 'SCORM package content',
            ],
            'scorm_with_query_params' => [
                'scormurl' => 'https://example.com/scorm/package.zip?token=abc123&version=1.2',
                'expectedresponse' => 'SCORM 1.2 package content',
            ],
            'scorm_xml_manifest' => [
                'scormurl' => 'https://example.com/scorm/imsmanifest.xml',
                'expectedresponse' => '<?xml version="1.0"?><manifest>...</manifest>',
            ],
        ];
    }

    /**
     * Data provider for invalid SCORM URLs
     */
    public static function provide_get_scorm_invalid_urls_data(): array {
        return [
            'empty_url' => [''],
            'invalid_url_format' => ['not-a-valid-url'],
            'missing_protocol' => ['example.com/scorm/package.zip'],
        ];
    }

    /**
     * Data provider for SCORM error responses
     */
    public static function provide_get_scorm_error_responses_data(): array {
        return [
            'not_found_404' => [
                'httpcode' => 404,
                'errorresponse' => '{"errors": [{"code": "404", "title": "Not Found", "detail": "SCORM package not found"}]}',
                'expectedexception' => '/404 Not Found SCORM package not found/',
            ],
            'forbidden_403' => [
                'httpcode' => 403,
                'errorresponse' => '{"errors": [{"code": "403", "title": "Forbidden", ' .
                    '"detail": "Access denied to SCORM resource"}]}',
                'expectedexception' => '/403 Forbidden Access denied to SCORM resource/',
            ],
            'server_error_500' => [
                'httpcode' => 500,
                'errorresponse' => 'Internal Server Error',
                'expectedexception' => '/unknown error/',
            ],
        ];
    }

    /**
     * Data provider for connection failure scenarios
     */
    public static function provide_can_connect_to_the_api_failure_data(): array {
        return [
            'unauthorized_401' => [
                'response' => '{"errors": [{"code": "401", "title": "Unauthorized", "detail": "Invalid credentials"}]}',
                'httpcode' => 401,
            ],
            'forbidden_403' => [
                'response' => '{"errors": [{"code": "403", "title": "Forbidden", "detail": "Access denied"}]}',
                'httpcode' => 403,
            ],
            'server_error_500' => [
                'response' => 'Internal Server Error',
                'httpcode' => 500,
            ],
        ];
    }

    /**
     * Data provider for get_catalogs error scenarios
     */
    public static function provide_get_catalogs_error_data(): array {
        return [
            'unauthorized_401' => [
                'httpcode' => 401,
                'errorresponse' => '{"errors": [{"code": "401", "title": "Unauthorized", "detail": "Invalid token"}]}',
                'expectedexception' => '/401 Unauthorized Invalid token/',
            ],
            'server_error_500' => [
                'httpcode' => 500,
                'errorresponse' => '{"errors": [{"code": "500", "title": "Internal Server Error", "detail": "Database error"}]}',
                'expectedexception' => '/500 Internal Server Error Database error/',
            ],
            'malformed_response' => [
                'httpcode' => 503,
                'errorresponse' => 'Service Unavailable',
                'expectedexception' => '/unknown error/',
            ],
        ];
    }
}
