<?php
  /**
   * Class MailchimpClient
   * Currently uses Mailchimps API version 3.0
   * https://developer.mailchimp.com/documentation/mailchimp/reference/overview/
   */
  class MailchimpClient {
    private $api_key;
    private $api_url;
    private $last_response;

    public function __construct($api_key) {
      $this->api_key = $api_key;
      $this->api_url = $this->getAPIurl($api_key);
    }

    /**
     * Checks if a provided Mailchimp API Key is valid
     * and creates the api_url to use if it is.
     * @param $api_key
     *
     * @return mixed|string
     */
    private function getAPIurl($api_key) {
      $dash_index = strpos($api_key, '-');
      if ($dash_index === false) {
        return 'This API Key is invalid';
      }

      return $this->api_url = str_replace('//api.', '//' . substr($api_key, $dash_index + 1) . '.api.',
        'https://api.mailchimp.com/3.0/');
    }

    /**
     * Builds the request for Mailchimp through WordPress
     * @param $method
     * @param $resource
     * @param array $data
     *
     * @return array|mixed|object
     * @throws Exception
     */
    private function request($method, $resource, $data = []) {
      // Resets the last request
      $this->reset();

      // No API, no conecto senor.
      if (empty($this->api_key)) {
        throw new Exception("Invalid MailChimp API key `{$this->api_key}` supplied.");
      }

      $url = $this->api_url . ltrim($resource, '/');
      $args = [
        'method' => $method,
        'headers' => $this->get_headers(),
        'timeout' => 10,
        'sslverify' => true
      ];

      // attach arguments (in body or URL)
      if ($method === 'GET') {
        $url = add_query_arg($data, $url);
      } else {
        $args['body'] = json_encode($data);
      }

      // perform request
      $response = wp_remote_request($url, $args);
      $this->last_response = $response;

      // parse response
      $data = $this->parse_response($response);

      return $data;
    }

    // resets the last_response
    private function reset() {
      $this->last_response = null;
    }

    /**
     * Builds the headers to pass in for current request
     * @return array
     */
    private function get_headers() {
      global $wp_version;

      $headers = [];
      $headers['Authorization'] = 'Basic ' . base64_encode('apikey:' . $this->api_key);
      $headers['Accept'] = 'application/json';
      $headers['Content-Type'] = 'application/json';
      $headers['User-Agent'] = 'MailChimp-API/3.0; WordPress/' . $wp_version . '; ' . get_bloginfo('url');

      if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $headers['Accept-Language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
      }

      return $headers;
    }

    /**
     * Makes sure our data is ready worthy for Mailchimp.
     * @param $response
     *
     * @return array|mixed|object
     * @throws MailchimpException
     */
    private function parse_response($response) {
      if ($response instanceof WP_Error) {
        throw new MailchimpException($response->get_error_message(), (int) $response->get_error_code());
      }

      // decode response body
      $code = (int) wp_remote_retrieve_response_code($response);
      $message = wp_remote_retrieve_response_message($response);
      $body = wp_remote_retrieve_body($response);

      // If mailchimp doesn't return content set body to true.
      if ($code < 300 && empty($body)) {
        $body = 'true';
      }

      $data = json_decode($body);

      if ($code >= 400) {
        throw new MailchimpException($message, $code, $response, $data);
      }

      if (!is_null($data)) {
        return $data;
      }

      // unable to decode response
      throw new MailchimpException($message, $code, $response);
    }

    // Make GET request for retrieving data
    public function get($resource, $args = []) {
      return $this->request('GET', $resource, $args);
    }

    // Make POST request for creating and updating dat
    public function post($resource, array $data) {
      return $this->request('POST', $resource, $data);
    }

    // Make PUT request for updating data
    public function put($resource, array $data) {
      return $this->request('PUT', $resource, $data);
    }

    // Make PATCH request for udpating partial data
    public function patch($resource, array $data) {
      return $this->request('PATCH', $resource, $data);
    }

    // Make DELETE request for deleting specific data
    public function delete($resource) {
      return $this->request('DELETE', $resource);
    }

    /**
     * Debugging: Get the last body response
     * @return string
     */
    public function get_last_response_body() {
      return wp_remote_retrieve_body($this->last_response);
    }

    /**
     * Debugging: Get the last header response
     * @return array
     */
    public function get_last_response_headers() {
      return wp_remote_retrieve_headers($this->last_response);
    }

    /**
     * Updated to the response in the request function
     * @return array|WP_Error
     */
    public function get_last_response() {
      return $this->last_response;
    }
  }
