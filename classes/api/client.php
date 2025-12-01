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

namespace mod_edflex\api;

use cache;
use curl;
use Generator;
use moodle_exception;

/**
 * Class to make EdFlex API requests
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** Get API access_token */
    const GET_ACCESS_TOKEN_URL = '%s/connect/v1/auth/token';

    /** Get Edflex contents */
    const GET_CONTENTS_URL = '%s/connect/v1/resources';

    /** Get Edflex categories */
    const GET_CATEGORIES_URL = '%s/connect/v1/catalogs/%s/categories';

    /** Get Edflex catalogs */
    const GET_CATALOGS_URL = '%s/connect/v1/catalogs';

    /** The maximum number of contents accepted by Edflex API for filtering by `filter[contentIds]` */
    const EDFLEX_CONTENTIDS_FILTER_LIMIT = 50;

    /**
     * @var false|mixed|object|string
     */
    private $apiurl;

    /**
     * @var false|mixed|object|string
     */
    private $clientid;

    /**
     * @var false|mixed|object|string
     */
    private $clientsecret;

    /**
     * @var cache
     */
    private $cache;

    /**
     * @var curl
     */
    private $curl;

    /**
     * Constructor
     *
     * @param string $clientid The client ID.
     * @param string $clientsecret The client secret.
     * @param string $apiurl The API URL.
     * @param curl $curl The cURL instance.
     * @param cache $cache The cache instance.
     */
    public function __construct(
        string $clientid,
        string $clientsecret,
        string $apiurl,
        curl $curl,
        cache $cache
    ) {
        if (empty($clientid)) {
            throw new moodle_exception('clientidinvalid', 'mod_edflex');
        }

        if (empty($clientsecret)) {
            throw new moodle_exception('clientsecretinvalid', 'mod_edflex');
        }

        if (empty($apiurl)) {
            throw new moodle_exception('apiurlinvalid', 'mod_edflex');
        }

        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
        $this->apiurl = rtrim($apiurl, '/');
        $this->curl = $curl;
        $this->cache = $cache;
    }

    /**
     * Creates an instance of the class using configuration settings.
     */
    public static function from_config(): self {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new self(
            get_config('mod_edflex', 'clientid'),
            get_config('mod_edflex', 'clientsecret'),
            get_config('mod_edflex', 'apiurl'),
            new curl(),
            cache::make('mod_edflex', 'api')
        );
    }

    /**
     * Creates an instance of the class from an array of credentials.
     *
     * @param array $credentials An associative array containing the necessary credentials:
     *                           - 'clientid': The client ID.
     *                           - 'clientsecret': The client secret.
     *                           - 'apiurl': The API URL.
     */
    public static function from_array(array $credentials): self {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new self(
            $credentials['clientid'] ?? null,
            $credentials['clientsecret'] ?? null,
            $credentials['apiurl'] ?? null,
            new curl(),
            cache::make('mod_edflex', 'api')
        );
    }

    /**
     * Retrieves content for the given array of IDs.
     *
     * This method takes an array of content IDs and fetches the corresponding content
     * for each ID. The resulting content is organized in an associative array,
     * where the keys are the IDs and the values are the respective content.
     *
     * @param array $contentids An array of content IDs for which content needs to be fetched.
     *
     * @return Generator An associative array with the content IDs as keys and their corresponding content as values.
     */
    public function get_contents_by_ids(array $contentids): Generator {
        $contentidschunks = array_chunk($contentids, self::EDFLEX_CONTENTIDS_FILTER_LIMIT);

        foreach ($contentidschunks as $chunk) {
            $params = ['contentIds' => $chunk];
            $contents = $this->get_contents($params)['data'] ?? [];

            foreach ($contents as $content) {
                yield $content['id'] => $content;
            }
        }
    }

    /**
     * Requests and retrieves an access token from the authentication server.
     *
     * This method sends a POST request to the authentication server to get
     * an access token using the client credentials grant type. The response
     * is validated to ensure it includes the access token and expiration time.
     *
     * @throws moodle_exception
     */
    public function request_access_token(): array {
        $this->curl->resetHeader();
        $this->curl->resetopt();

        $now = time();
        $url = sprintf(self::GET_ACCESS_TOKEN_URL, $this->apiurl);

        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
        ];

        $response = $this->curl->post($url, $params);
        $response = $this->parse_response($response, $this->curl->get_info());

        $isaccesstokenok = !empty($response['access_token']) && !empty($response['expires_in']);

        if (!$isaccesstokenok) {
            throw new moodle_exception('invalidaccesstoken');
        }

        $accesstokenvalidtime = max(30, $response['expires_in'] - 30);
        $response['expire_ts'] = $now + $accesstokenvalidtime;
        $this->cache->set('access_token', $response);

        return $response;
    }

    /**
     * Retrieves the access token from the cache, or requests a new one if not available or expired.
     *
     * This method checks if the access token is present in the cache and valid. If the token is not
     * found or has expired, it will initiate a request to retrieve a new access token. The method
     * ensures that outdated tokens are removed from the cache automatically.
     */
    public function get_access_token(): string {
        $this->delete_access_token_from_cache_if_expired();

        $response = $this->cache->get('access_token');

        if (!$response) {
            $response = $this->request_access_token();
        }

        return $response['access_token'];
    }

    /**
     * Deletes the access token from the cache if it is expired.
     */
    private function delete_access_token_from_cache_if_expired(): void {
        $response = $this->cache->get('access_token');

        $expirets = $response['expire_ts'] ?? 0;
        $isaccesstokenexpired = $expirets <= time();

        if ($isaccesstokenexpired) {
            $this->cache->delete('access_token');
        }
    }

    /**
     * Fetches the SCORM data from the provided URL.
     *
     * This method retrieves SCORM data by sending a GET request to the given URL. URL validation
     * is performed to ensure its correctness. A valid access token is included in the request headers.
     * An exception is thrown if the URL is invalid or the response contains errors.
     *
     * @param string $scormurl The URL to fetch SCORM data from. It must be a valid and properly formatted URL.
     * @return string The SCORM data retrieved from the provided URL.
     * @throws moodle_exception If the provided URL is invalid or the response contains errors.
     */
    public function get_scorm(string $scormurl): string {
        $scormurl = trim($scormurl);

        if (empty($scormurl) || !filter_var($scormurl, FILTER_VALIDATE_URL)) {
            throw new moodle_exception('invalidscormurlprovided', 'mod_edflex');
        }

        $headers = [
            'Authorization: Bearer ' . $this->get_access_token(),
            'Content-Type: application/json',
        ];

        $this->curl->setHeader($headers);

        $response = $this->curl->get($scormurl);

        return $this->parse_response($response, $this->curl->get_info(), false);
    }

    /**
     * Retrieves a list of contents based on specified filters, pagination, and per-page parameters.
     *
     * This method sends a request to the API, applying any provided filters and pagination options.
     * Filters include query, type, language, and category. The method also validates the API response
     * and handles invalid responses or errors appropriately.
     *
     * @param array $filters An associative array of filters including:
     *                       - string 'query': Search query string.
     *                       - string 'type': Type of content to filter.
     *                       - string 'language': Language code to filter by content language.
     *                       - string 'category': Category ID to filter by content category.
     *                       - array 'contentIds': Content IDs to filter by content category.
     * @param ?int $page The page number for the paginated results, defaults to 1.
     * @param ?int $limit The number of items per page, defaults to 50.
     *
     * @return array The structured API response containing the retrieved contents.
     *               Handles errors or invalid responses by throwing exceptions.
     */
    public function get_contents(array $filters, ?int $page = 1, ?int $limit = 50): array {
        $params = [
            'page' => ['number' => $page, 'size' => $limit],
            'filter' => [],
        ];

        if (!empty($filters['query'])) {
            $params['filter']['query'] = clean_param($filters['query'], PARAM_TEXT);
        }

        if (!empty($filters['type'])) {
            $params['filter']['type'] = clean_param($filters['type'], PARAM_TEXT);
        }

        if (!empty($filters['language'])) {
            $params['filter']['language'] = clean_param($filters['language'], PARAM_TEXT);
        }

        if (!empty($filters['category'])) {
            $params['filter']['categoryId'] = clean_param($filters['category'], PARAM_TEXT);
        }

        $contentids = $filters['contentIds'] ?? [];

        if ($contentids) {
            if (is_string($contentids)) {
                $contentids = explode(',', $contentids);
            }

            foreach ($contentids as $idx => $contentid) {
                $cleancontentid = clean_param($contentid, PARAM_ALPHANUMEXT);

                if ($cleancontentid != $contentid) {
                    throw new moodle_exception('invalidcontentid', 'mod_edflex');
                }

                $contentids[$idx] = $cleancontentid;
            }

            $contentids = array_filter($contentids);

            if (empty($contentids)) {
                throw new moodle_exception('invalidcontentid', 'mod_edflex');
            }

            $params['filter']['contentIds'] = implode(',', $contentids);
        }

        $this->curl->setHeader([
            'Authorization: Bearer ' . $this->get_access_token(),
            'Content-Type: application/json',
        ]);
        $url = sprintf(self::GET_CONTENTS_URL, $this->apiurl);

        $response = $this->curl->get($url, $params);

        return $this->parse_response($response, $this->curl->get_info());
    }

    /**
     * Retrieves the list of categories for a given catalog ID.
     *
     * This method sends a GET request to the categories API endpoint to fetch the categories
     * associated with a specific catalog. It uses the access token for authorization and expects
     * a properly formatted response in JSON. If the response is invalid, an exception will be thrown.
     *
     * @param string $catalogid The catalog ID.
     * @param array $filters The filters to apply to the categories.
     * @param int $page The page number.
     * @param int $perpage The number of categories per page.
     *
     * @return array The list of categories.
     */
    public function get_categories(
        string $catalogid,
        array $filters = [],
        int $page = 1,
        int $perpage = 50
    ): array {
        $params = [
            'page' => ['number' => $page, 'size' => $perpage],
            'filter' => [],
        ];

        if (!empty($filters['nestingLevel'])) {
            $params['filter']['nestingLevel'] = clean_param($filters['nestingLevel'], PARAM_INT);
        }

        $this->curl->setHeader([
            'Authorization: Bearer ' . $this->get_access_token(),
            'Content-Type: application/json',
        ]);

        $url = sprintf(self::GET_CATEGORIES_URL, $this->apiurl, $catalogid);
        $response = $this->curl->get($url, $params);

        return $this->parse_response($response, $this->curl->get_info());
    }

    /**
     * Fetches a list of catalogs from the API or retrieves them from the cache.
     *
     * This method attempts to retrieve catalogs from the cache if a time-to-live (TTL) value is provided. If the cached
     * catalogs have expired or are not available, it sends an API request to fetch the catalogs. The result is then stored
     * in the cache with the specified TTL for future use. If the API response is invalid or contains errors, an exception
     * is thrown.
     *
     * @param int $ttl The time-to-live in seconds for caching the catalogs.
     *                  Defaults to 3600 seconds. A value of 0 disables caching.
     *
     * @return array The list of catalogs retrieved from the API or cache.
     *
     * @throws moodle_exception If the API response is invalid or contains errors.
     */
    public function get_catalogs(int $ttl = 3600): array {
        $catalogs = [];
        $now = time();

        if ($ttl) {
            [$catalogs, $expirets] = $this->cache->get('catalogs');

            if ($expirets <= $now) {
                $catalogs = [];
            }
        }

        if (!empty($catalogs)) {
            return $catalogs;
        }

        $this->curl->setHeader([
            'Authorization: Bearer ' . $this->get_access_token(),
            'Content-Type: application/json',
        ]);

        $url = sprintf(self::GET_CATALOGS_URL, $this->apiurl);
        $response = $this->curl->get($url);

        $response = $this->parse_response($response, $this->curl->get_info());

        if ($ttl > 0) {
            $this->cache->set('catalogs', [$response, $now + $ttl]);
        }

        return $response;
    }

    /**
     * Checks whether the application can successfully connect to the API.
     *
     * This method attempts to retrieve an access token from the API. If a valid access token
     * is obtained, the connection is considered successful. In case of an exception or if no
     * access token is received, the connection is deemed unsuccessful.
     *
     * @return bool True if the connection to the API is successful, false otherwise.
     */
    public function can_connect_to_the_api(): bool {
        try {
            $response = $this->request_access_token();
        } finally {
            return !empty($response['access_token']);
        }
    }

    /**
     * Parses the raw API response, validates it, and decodes it if required.
     *
     * This method processes the raw response string and HTTP response information. It validates
     * the HTTP status code and decodes the response as JSON if expected. If the response indicates
     * errors or the JSON decoding fails, appropriate exceptions are thrown with error messages.
     *
     * @param string $rawresponse The raw response string received from the API.
     * @param array $responseinfo An associative array containing HTTP response details, such as 'http_code'.
     * @param bool $expectedjson Indicates whether the response is expected to be JSON. Defaults to true.
     * @return mixed The parsed response object/array if the response is JSON,
     *               or the raw response string if JSON is not expected.
     * @throws moodle_exception Thrown when the HTTP status code indicates an error or
     *                          the response indicates errors or JSON parsing fails.
     */
    public function parse_response(string $rawresponse, array $responseinfo, bool $expectedjson = true) {
        $statuscode = $responseinfo['http_code'];

        if ($statuscode < 200 || $statuscode >= 300) {
            $response = json_decode($rawresponse, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($response['errors'])) {
                $errormessage = $this->build_error_message_from_response($response['errors']);
            } else {
                $errormessage = 'unknown error';
            }

            throw new moodle_exception($errormessage);
        }

        if (!$expectedjson) {
            return $rawresponse;
        }

        $response = json_decode($rawresponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errormessage = get_string('invalidapiresponse', 'mod_edflex') .
                ' ' . json_last_error_msg() . ' Raw response: ' . $rawresponse;
        } else if (!empty($response['errors'])) {
            $errormessage = $this->build_error_message_from_response($response['errors']);
        }

        if (!empty($errormessage)) {
            throw new moodle_exception($errormessage);
        }

        return $response;
    }

    /**
     * Constructs a formatted error message string from an array of error responses.
     *
     * This method iterates through a collection of error details and combines their components
     * (code, title, detail) into a single string. Each error's information is separated by a period
     * if there are multiple errors in the input.
     *
     * @param array $errors An array of error responses, where each error may contain 'code', 'title',
     *                      and 'detail' keys.
     *
     * @return string A concatenated error message string generated from the input errors.
     */
    public function build_error_message_from_response(array $errors): string {
        return array_reduce(
            $errors,
            static function ($carry, $error) {
                $code = $error['code'] ?? '';
                $title = $error['title'] ?? '';
                $detail = $error['detail'] ?? '';
                $separator = empty($carry) ? '' : '. ';

                return "{$carry}{$separator}{$code} {$title} {$detail}";
            },
            ''
        );
    }
}
