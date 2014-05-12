<?php
/**
 * TrueVault PHP client library
 * Curl-Free Version
 * More information at https://www.truevault.com/
 *
 * @author Marek Vavrecan <vavrecan@gmail.com>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @version 1.0.3
 */

if (!function_exists('json_decode')) {
    throw new Exception('TrueVault needs the JSON PHP extension.');
}

class TrueVaultException extends Exception
{
    /**
     * The result from the API server that represents the exception information.
     * @var mixed
     */
    protected $result;

    /**
     * Type for the error
     * @var string
     */
    protected $type;

    /**
     * Make a new Exception with the result
     * @param string $message
     * @param int $code
     * @param string $type
     * @internal param array $result The result from the API server
     */
    public function __construct($message, $code, $type = "Exception") {
        $this->message = $message;
        $this->code = $code;
        $this->type = $type;
    }

    /**
     * Return the associated result object
     * @return array The result from the API server
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * Returns the associated type for the error
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Pretty output
     * @return string
     */
    public function __toString() {
        return $this->type . ": " . $this->message;
    }
}

class TrueVault {
    const VERSION = "1.0.4";
    const API_VERSION = "v1";

    /**
     * Default options
     * @var array
     */
    public static $options = array(
        "timeout"   => 10,
        "useragent" => "truevault-php"
    );

    /**
     * @var string
     */
    public static $API_ENDPOINT = "https://api.truevault.com";

    /**
     * @var bool
     */
    protected $debug = false;


    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @param bool $debug
     */
    public function setDebug($debug) {
        $this->debug = $debug;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * @return string
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * @var string
     */
    protected $accountId;

    /**
     * @param mixed $accountId
     */
    public function setAccountId($accountId) {
        $this->accountId = $accountId;
    }

    /**
     * @return mixed
     */
    public function getAccountId() {
        return $this->accountId;
    }

    /**
     * Initialize a TrueVault.
     *
     * The configuration:
     * - apiKey: TrueVault application key
     * - accountId: TrueVault account ID
     *
     * @param array $config The application configuration
     */
    public function __construct($config) {
        $this->setApiKey($config["apiKey"]);
        $this->setAccountId($config["accountId"]);
    }

    /**
     * Build the URL for path and parameters.
     *
     * @param string $path   Optional path (without a leading slash)
     * @param array  $params Optional query parameters
     *
     * @return string The URL for the parameters
     */
    protected function getUrl($path = "", $params = array()) {
        $url = self::$API_ENDPOINT . "/" . self::API_VERSION . "/";

        if ($path) {
            if ($path{0} === "/") $path = substr($path, 1);
            $url .= $path;
        }

        if ($params) {
            $url .= "?" . http_build_query($params, null, "&");
        }

        return $url;
    }

    /**
     * Invoke the API.
     *
     * @param string $path The path (required)
     * @param string $method The http method (default 'GET')
     * @param array $params The query/post data
     * @param array $transfer Containing source file to upload (array key upload) or download (array key download)
     *
     * @return mixed The decoded response object
     * @throws TrueVaultException
     * @throws Exception
     */
    public function api($path, $method = "GET", $params = array(), $transfer = array())
    {
        $url = $this->getUrl($path);

        // set upload mode
        if (array_key_exists("upload", $transfer)) {
            $file = $transfer["upload"];
            throw new Exception("Not available in Curl-free version");
        }

        // set download mode
        if (array_key_exists("download", $transfer)) {
            $file = $transfer["download"];
            throw new Exception("Not available in Curl-free version");
        }

        $opts = array(
            "http" => array(
                "timeout" => self::$options["timeout"],
                "ignore_errors" => true,
                "method" => $method,
                "header" => "Accept-language: en\r\n" .
                    "User-Agent: " . self::$options["useragent"] . "\r\n" .
                    "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Authorization: Basic " . base64_encode($this->getApiKey() . ":") . "\r\n",

                "content" => http_build_query($params, null, '&')
            )
        );

        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        $headers = $http_response_header;

        $contentType = "";
        $httpCode = 500;

        if (is_array($headers) && count($http_response_header) > 0) {
            if (preg_match('#HTTP/[0-9\.]+ (\d+)#', $http_response_header[0], $matches)) {
                $httpCode = $matches[1];
            }

            foreach ($headers as $header) {
                if (preg_match("#^Content-type: (.+)$#i", $header, $matches))
                    $contentType = $matches[1];
            }
        }

        // decode response if returned as json
        if ($contentType == "application/json")
            $result = json_decode($result, true);

        // handle error of no result
        if ($result === false) {
            $e = new TrueVaultException("Unable to retrieve post data", 0, "Exception");
            throw $e;
        }

        // handle error within response
        if (is_array($result) && array_key_exists("error", $result)) {
            // make sure all required keys are present
            if (!array_key_exists("message", $result["error"]))
                $result["error"]["message"] = "";

            if (!array_key_exists("code", $result["error"]))
                $result["error"]["code"] = "";

            if (!array_key_exists("type", $result["error"]))
                $result["error"]["type"] = "";

            $e = new TrueVaultException($result["error"]["message"], $result["error"]["code"], $result["error"]["type"]);
            throw $e;
        }

        // handle error 500
        if ($httpCode == 500) {
            $e = new TrueVaultException("Remote server returned internal error", 0, "RemoteException");
            throw $e;
        }

        return $result;
    }

    /**
     * Obtain list of vaults for account Id
     * @return mixed
     * @throws TrueVaultException
     */
    public function findAllVaults() {
        $vaults = $this->api("accounts/{$this->getAccountId()}/vaults");

        if (isset($vaults["vaults"]))
            return $vaults["vaults"];

        throw new TrueVaultException("Unable to obtain list of vaults", 0);
    }

    /**
     * Return TrueVault document handler
     * @param $vaultId
     * @return TrueVaultDocuments
     */
    public function documents($vaultId) {
        $trueVaultDocuments = new TrueVaultDocuments($this, $vaultId);
        return $trueVaultDocuments;
    }


    /**
     * Return TrueVault schema handler
     * @param $vaultId
     * @return TrueVaultSchemas
     */
    public function schemas($vaultId) {
        $trueVaultSchemas = new TrueVaultSchemas($this, $vaultId);
        return $trueVaultSchemas;
    }

    /**
     * Return TrueVault blob handler (file storage)
     * @param $vaultId
     * @return TrueVaultSchemas
     */
    public function blobs($vaultId) {
        $trueVaultBlobs = new TrueVaultBlobs($this, $vaultId);
        return $trueVaultBlobs;
    }

    /**
     * Prints to the error log if you are not in command line mode.
     * @param string $msg Log message
     */
    protected static function errorLog($msg) {
        if (php_sapi_name() != 'cli') {
            error_log($msg);
        }
    }

    /**
     * Encode data
     * @param mixed $data
     * @return string
     */
    public function encodeData($data) {
        return base64_encode(json_encode($data));
    }

    /**
     * Decode data
     * @param string $data
     * @return mixed
     */
    public function decodeData($data) {
        return json_decode(base64_decode($data), true);
    }
}

abstract class TrueVaultStores
{
    /**
     * @var TrueVault API connection class
     */
    protected $trueVault;

    /**
     * @var string ID of the vault
     */
    protected $vaultId;

    /**
     * @var string last created object ID
     */
    protected $lastId;

    public function lastInsertId() {
        return $this->lastId;
    }

    public function setVaultId($vaultId) {
        $this->vaultId = $vaultId;
    }

    public function getVaultId() {
        return $this->vaultId;
    }

    public function __construct($trueVault, $vaultId) {
        $this->trueVault = $trueVault;
        $this->vaultId = $vaultId;
    }
}

class TrueVaultDocuments extends TrueVaultStores
{
    /**
     * Create new document
     * @param mixed $data
     * @param array $params
     * @return mixed
     */
    public function create($data, $params = array()) {
        $this->lastId = null;

        $params["document"] = $this->trueVault->encodeData($data);
        $return = $this->trueVault->api("vaults/{$this->vaultId}/documents", "POST", $params);

        if (array_key_exists("document_id", $return))
            $this->lastId = $return["document_id"];

        return $return;
    }

    /**
     * Get document data
     * @param string|array $documentId single document or multiple return
     * @param array $params
     * @throws TrueVaultException
     * @return string
     */
    public function get($documentId, $params = array()) {
        // join multiple
        if (is_array($documentId))
            $documentId = join(",", $documentId);

        $response = $this->trueVault->api("vaults/{$this->vaultId}/documents/{$documentId}", "GET", $params);

        // return single document
        if (is_string($response)) {
            return $this->trueVault->decodeData($response);
        }

        // return multiple documents
        if (is_array($response) && array_key_exists("documents", $response)) {
            $list = array();

            foreach ($response["documents"] as $document) {
                $list[$document["id"]] = $this->trueVault->decodeData($document["document"]);
            }

            return $list;
        }

        throw new TrueVaultException("Unable to obtain document", 0);
    }

    /**
     * Delete document
     * @param string $documentId
     * @param array $params
     * @return mixed
     */
    public function delete($documentId, $params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/documents/{$documentId}", "DELETE", $params);
        return $response;
    }

    /**
     * Update document data
     * @param string $documentId
     * @param mixed $data
     * @param array $params
     * @return mixed
     */
    public function update($documentId, $data, $params = array()) {
        $params["document"] = $this->trueVault->encodeData($data);
        return $this->trueVault->api("vaults/{$this->vaultId}/documents/{$documentId}", "PUT", $params);
    }

    public function search($searchOptions, $params = array()) {
        $search = $this->trueVault->encodeData($searchOptions);
        $response = $this->trueVault->api("vaults/{$this->vaultId}/?search_option={$search}", "GET", $params);

        if (array_key_exists("data", $response))
            return $response["data"];

        return $response;
    }
}

class TrueVaultSchemas extends TrueVaultStores
{
    /**
     * Create new schema
     * @param mixed $data
     * @param array $params
     * @return mixed
     */
    public function create($data, $params = array()) {
        $this->lastId = null;

        $params["schema"] = $this->trueVault->encodeData($data);
        $response = $this->trueVault->api("vaults/{$this->vaultId}/schemas", "POST", $params);

        if (array_key_exists("schema", $response)) {
            $this->lastId = $response["schema"]["id"];
            return $response["schema"];
        }

        return $response;
    }

    /**
     * Get schema data
     * @param string $schemaId
     * @param array $params
     * @throws TrueVaultException
     * @return string
     */
    public function get($schemaId, $params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/schemas/{$schemaId}", "GET", $params);

        if (is_array($response) && array_key_exists("schema", $response))
            return $response["schema"];

        throw new TrueVaultException("Unable to obtain schema", 0);
    }

    /**
     * Delete schema
     * @param string $schemaId
     * @param array $params
     * @return mixed
     */
    public function delete($schemaId, $params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/schemas/{$schemaId}", "DELETE", $params);
        return $response;
    }

    /**
     * Update schema data
     * @param string $schemaId
     * @param mixed $data
     * @param array $params
     * @return mixed
     */
    public function update($schemaId, $data, $params = array()) {
        $params["schema"] = $this->trueVault->encodeData($data);
        return $this->trueVault->api("vaults/{$this->vaultId}/schemas/{$schemaId}", "PUT", $params);
    }

    /**
     * List all schemas
     * @param array $params
     * @throws TrueVaultException
     * @return array
     */
    public function findAll($params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/schemas", "GET", $params);

        // get all schemas
        if (is_array($response) && array_key_exists("schemas", $response)) {
            $list = array();

            foreach ($response["schemas"] as $schema) {
                $list[$schema["id"]] = $schema["name"];
            }

            return $list;
        }

        throw new TrueVaultException("Unable to obtain schema", 0);
    }
}

class TrueVaultBlobs extends TrueVaultStores
{
    /**
     * Create new or replace existing BLOB store
     * @param mixed $file
     * @param string $blobId if specified existing blob will be replaced
     * @param array $params
     * @return array
     */
    public function upload($file, $blobId = null, $params = array()) {
        // replace existing
        if ($blobId) {
            $response = $this->trueVault->api("vaults/{$this->vaultId}/blobs/{$blobId}", "PUT",
                $params, array("upload" => $file));

            return $response;
        }

        // create new
        $this->lastId = null;
        $response = $this->trueVault->api("vaults/{$this->vaultId}/blobs", "POST",
            $params, array("upload" => $file));

        if (array_key_exists("blob_id", $response)) {
            $this->lastId = $response["blob_id"];
            return $response;
        }

        return $response;
    }

    /**
     * Download BLOB store data to file
     * @param string $blobId
     * @param string $file
     * @param array $params
     * @throws TrueVaultException
     * @return mixed
     */
    public function download($blobId, $file, $params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/blobs/{$blobId}", "GET",
            $params, array("download" => $file));

        return $response;
    }

    /**
     * Delete BLOB store
     * @param string $blobId
     * @param array $params
     * @return array
     */
    public function delete($blobId, $params = array()) {
        $response = $this->trueVault->api("vaults/{$this->vaultId}/blobs/{$blobId}", "DELETE", $params);
        return $response;
    }
}