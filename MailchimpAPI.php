<?php

  class MailchimpAPI {
    protected $client;
    public $error_message = '';
    public $error_code = '';

    public function __construct($api_key) {
      $this->client = new MailchimpClient($api_key);
    }

    /**
     * Formats email address exactly as mailchimp requires it.
     *
     * @param $email_address
     * @return string
     */
    public function get_subscriber_hash($email_address) {
      return md5(strtolower(trim($email_address)));
    }

    /**
     * Gets list merge fields (if any)
     *
     * @param $list_id
     * @param array $args
     * @return array
     */
    public function get_list_merge_fields($list_id, $args = []) {
      $data = $this->client->get(sprintf('/lists/%s/merge-fields', $list_id), $args);

      if (is_object($data) && isset($data->merge_fields)) {
        return $data->merge_fields;
      }

      return [];
    }

    /**
     * returns details of a specific single list
     * and can return/check list against array of arguments
     *
     * @param $list_id
     * @return object[]
     */
    public function get_list($list_id) {
      return $this->client->get(sprintf('/lists/%s', $list_id));
    }

    /**
     * returns details of a all lists and
     * can return/check lists against array of arguments
     *
     * @param array $args
     * @return array
     */
    public function get_lists($args = []) {
      $data = $this->client->get('/lists', $args);

      if (is_object($data) && isset($data->lists)) {
        return $data->lists;
      }

      return [];
    }

    /**
     * Creates a list based on array of arguments
     *
     * @param array $args
     * @return array
     */
    public function create_list($args = []) {
      return $this->client->post('/lists', $args);
    }

    /**
     * Update/Edit a specific list based on array of arguments
     *
     * @param $list_id
     * @param array $args
     * @return array
     */
    public function update_list($list_id, $args = []) {
      $data = $this->client->patch(sprintf('/lists/%s', $list_id), $args);

      if (is_object($data) && isset($data->lists)) {
        return $data->lists;
      }

      return [];
    }

    /**
     * Delete list by id
     *
     * @param $list_id
     * @return array
     */
    public function delete_list($list_id) {
      $data = $this->client->delete(sprintf('/lists/%s', $list_id));

      if (is_object($data) && isset($data->lists)) {
        return $data->lists;
      }

      return [];
    }

    /**
     * Add a subscriber to a list based on
     * list id and array of details of subscriber.
     * !! If subscriber already exists, this updates them (except email)
     *
     * @param $list_id
     * @param $args
     * @return object[]
     */
    public function add_list_member($list_id, $args) {
      $subscriber_hash = $this->get_subscriber_hash($args['email_address']);

      // Mailchimp object requirement check
      if (isset($args['merge_fields'])) {
        $args['merge_fields'] = (object) $args['merge_fields'];
      }

      return $this->client->put(sprintf('/lists/%s/members/%s', $list_id, $subscriber_hash), $args);
    }

    /**
     * Gets details of specific list member in specific list
     *
     * @param $list_id
     * @param $email_address
     * @param array $args
     * @return object[]
     */
    public function get_list_member($list_id, $email_address, $args = []) {
      $subscriber_hash = $this->get_subscriber_hash($email_address);

      return $this->client->get(sprintf('/lists/%s/members/%s', $list_id, $subscriber_hash), $args);
    }

    /**
     * Update subscriber in a specific list with
     * the arguments passed in by the array.
     *
     * @param $list_id
     * @param $email_address
     * @param $args
     * @return object[]
     */
    public function update_list_member($list_id, $email_address, $args) {
      $subscriber_hash = $this->get_subscriber_hash($email_address);

      // Mailchimp object requirement check
      if (isset($args['merge_fields'])) {
        $args['merge_fields'] = (object) $args['merge_fields'];
      }

      return $this->client->put(sprintf('/lists/%s/members/%s', $list_id, $subscriber_hash), $args);
    }

    /**
     * Delete subscriber from the list
     * @param $list_id
     * @param $email_address
     *
     * @return bool
     */
    public function delete_list_member($list_id, $email_address) {
      $subscriber_hash = $this->get_subscriber_hash($email_address);
      $data = $this->client->delete(sprintf('/lists/%s/members/%s', $list_id, $subscriber_hash));

      // http://stackoverflow.com/a/2127324
      return !!$data;
    }

    /**
     * Checks if the user with email address
     * is also a subscriber of the provided list.
     * @param $list_id
     * @param $email_address
     *
     * @return bool
     */
    public function is_member($list_id, $email_address) {
      try {
        $data = $this->get_list_member($list_id, $email_address);
        return !empty($data->id);
      } catch (MailchimpException $error) {
        $this->error_code = $error->getCode();
        $this->error_message = $error;
        return false;
      }
    }

    public function is_subscriber($list_id, $email_address) {
      if ($this->is_member($list_id, $email_address)) {
        $member = $this->get_list_member($list_id, $email_address);
        return $member->status === 'subscribed';
      } else {
        return false;
      }
    }

    public function subscribe($list_id, array $args = []) {
      $is_subscriber = $this->is_subscriber($list_id, $args['email_address']);
      $is_member =$this->is_member($list_id, $args['email_address']);

      if (!$is_subscriber && $is_member) {
        return $this->update_list_member($list_id, $args['email_address'],  ['status' => 'subscribed']);

      } else if (!$is_subscriber && !$is_member) {
        return $this->add_list_member($list_id, $args);
      }

      return [];
    }

    public function unsubscribe($list_id, $email_address) {
      $is_subscriber = $this->is_subscriber($list_id, $email_address);

      if ($is_subscriber) {
        return $this->update_list_member($list_id, $email_address, ['status' => 'unsubscribed']);
      }

      return [];

    }

    /**
     * Debugging: Returns the details of last response body.
     * @return string
     */
    public function get_last_response_body() {
      return $this->client->get_last_response_body();
    }

    /**
     * Debugging: Returns array details of last header response.
     * @return array
     */
    public function get_last_response_headers() {
      return $this->client->get_last_response_headers();
    }

    /**
     * Resets error properties.
     */
    public function reset_error() {
      $this->error_message = '';
      $this->error_code = '';
    }

    /**
     * @return string
     */
    public function get_error_message() {
      return $this->error_message;
    }

    /**
     * @return string
     */
    public function get_error_code() {
      return $this->error_code;
    }
  }
