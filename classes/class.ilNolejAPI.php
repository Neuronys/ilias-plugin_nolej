<?php

/**
 * This file is part of Nolej Repository Object Plugin for ILIAS,
 * developed by OC Open Consulting to integrate ILIAS with Nolej
 * software by Neuronys.
 *
 * @author Vincenzo Padula <vincenzo@oc-group.eu>
 * @copyright 2024 OC Open Consulting SB Srl
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once "./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej/vendor/autoload.php";

/**
 * This class provides common methods to interact with Nolej REST API.
 */
class ilNolejAPI
{
    public const API_URL = "https://api-live.nolej.io";
    // public const API_URL = "https://api-staging.nolej.io";
    // public const API_URL = "https://api-dev.nolej.io";

    /** @var string */
    private $apikey;

    /**
     * @param string $apikey
     */
    public function __construct($apikey)
    {
        $this->apikey = $apikey;
    }

    /**
     * @param string $path
     * @param array $data
     * @param bool $decode
     */
    public function post($path, $data = array(), $decode = true)
    {
        $data_json = json_encode($data);
        $url = self::API_URL . $path;

        $client = new GuzzleHttp\Client(['http_errors' => false]);
        $response = $client->request("POST", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . $this->apikey,
                "User-Agent" => "ILIAS Plugin",
                "Content-Type" => "application/json"
            ],
            "body" => $data_json
        ]);

        if (!$decode) {
            return $response->getBody();
        }

        $object = json_decode($response->getBody());
        return $object !== null ? $object : $response->getBody();
    }

    /**
     * Put to Nolej server
     * @param string $path
     * @param mixed $data
     * @param bool $encode input's data
     * @param bool $decode output
     */
    public function put($path, $data = array(), $encode = false, $decode = true)
    {
        $data_json = $encode ? json_encode($data) : $data;
        $url = self::API_URL . $path;

        $client = new GuzzleHttp\Client(['http_errors' => false]);
        $response = $client->request("PUT", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . $this->apikey,
                "User-Agent" => "ILIAS Plugin",
                "Content-Type" => "application/json"
            ],
            "body" => $data_json
        ]);

        if (!$decode) {
            return $response->getBody();
        }

        $object = json_decode($response->getBody());
        return $object !== null ? $object : $response->getBody();
    }

    /**
     * @param string $path
     * @param mixed $data
     * @param bool $encodeInput
     * @param bool $decodeOutput
     *
     * @return object|string return the result given by Nolej. If
     * $decodeOutput is true, treat the result as json object and decode it.
     */
    public function get(
        $path,
        $data = "",
        $encodeInput = false,
        $decodeOutput = true
    ) {
        $url = self::API_URL . $path;
        $encodedData = $encodeInput ? json_encode($data) : $data;

        $client = new GuzzleHttp\Client(['http_errors' => false]);
        $response = $client->request("GET", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . $this->apikey,
                "User-Agent" => "ILIAS Plugin",
                "Content-Type" => "application/json"
            ],
            "body" => $encodedData
        ]);

        if (!$decodeOutput) {
            return $response->getBody();
        }

        $object = json_decode($response->getBody());
        return $object !== null ? $object : $response->getBody();
    }
}
