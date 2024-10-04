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

/**
 * This class provides common methods to interact with Nolej REST API.
 */
class ilNolejAPI
{
    /** @var string Nolej API endpoint */
    public const API_URL = "https://api-live.nolej.io";

    /** @var string[] Allowed audio formats */
    public const TYPE_AUDIO = ["mp3", "wav", "opus", "ogg", "oga", "m4a", "aiff"];

    /** @var string[] Allowed video formats */
    public const TYPE_VIDEO = ["m4v", "mp4", "webm", "mpeg"];

    /** @var string[] Allowed document formats */
    public const TYPE_DOC = ["pdf", "doc", "docx", "odt"];

    /** @var string[] Allowed text file formats */
    public const TYPE_TEXT = ["txt", "htm", "html"];

    /** @var string[] Allowed formats */
    public const TYPE_SUPPORTED = [
        ...self::TYPE_AUDIO,
        ...self::TYPE_VIDEO,
        ...self::TYPE_DOC,
        ...self::TYPE_TEXT
    ];

    /** @var int Max bytes for uploaded files (500 MB) */
    public const MAX_SIZE = 524288000;

    /** @var ?string */
    private static $key = null;

    /**
     * API class constructor.
     */
    public function __construct()
    {
        self::$key = self::getKey();
    }

    /**
     * Get the saved API key.
     * @return bool
     */
    public static function getKey()
    {
        if (self::$key == null) {
            self::$key = ilNolejPlugin::getConfig("api_key", "");
        }
        return self::$key;
    }

    /**
     * Check that the API key has been set.
     * @return bool
     */
    public static function hasKey()
    {
        return !empty(self::getKey());
    }

    /**
     * @param string $path
     * @param array $data
     * @param bool $decode
     */
    public function post($path, $data = [], $decode = true)
    {
        $data_json = json_encode($data);
        $url = self::API_URL . $path;

        $client = new GuzzleHttp\Client(["http_errors" => false]);
        $response = $client->request("POST", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . self::$key,
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
    public function put($path, $data = [], $encode = false, $decode = true)
    {
        $data_json = $encode ? json_encode($data) : $data;
        $url = self::API_URL . $path;

        $client = new GuzzleHttp\Client(["http_errors" => false]);
        $response = $client->request("PUT", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . self::$key,
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

        $client = new GuzzleHttp\Client(["http_errors" => false]);
        $response = $client->request("GET", $url, [
            "headers" => [
                "Authorization" => "X-API-KEY " . self::$key,
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
