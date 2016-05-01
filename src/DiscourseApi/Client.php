<?php

namespace DiscourseApi;

class Client
{
	private $client = null;
	private $config = null;

	public function __construct( Config $config )
	{
		$this->config = $config;
		$this->client = new \GuzzleHttp\Client([
			'base_uri' => $this->config->base_url,
			'timeout' => $this->config->timeout,
		]);
		if ($this->client === null) {
			throw new \Exception('Improperly loaded or Guzzle not found!');
		}
	}

	public function request($method, $path, $data = [])
	{
		$data = array_merge( [ 'is_json' => true, 'query' => [], 'body' => '', 'form_params' => [] ], $data );

		$params = [];

		// Headers
		$params['headers'] = [
			'User-Agent' => $this->config->useragent,
		];
		if ($this->config->passthrough) {
			$params['headers']['X-Forwarded-For'] = $this->config->xff;
		}

		// Query
		$params['query'] = [
			'api_key' => $this->config->key,
			'api_username' =>  $this->config->username,
		];
		$params['query'] = array_merge( $params['query'], $data['query'] );

		// Body
		if ( $data['body'] ) {
			$params['body'] = $data['body'];
		}
		if ( $data['form_params'] ) {
			$params['form_params'] = $data['form_params'];
		}

		$response = $this->client->request( $method, $path, $params );

		if ( $response && $response->getStatusCode() == 200 ) {
			if ($data['is_json']) {
				return json_decode( (string) $response->getBody() );
			} else {
				return $response;
			}
		} else {
			throw new \Exception( "Discourse API request '$path' failed!" );
		}
	}

	private function challenge()
	{
		try {
			$challenge = $this->request('GET', '/users/hp.json');
			if (!empty($challenge['value']) && !empty($challenge['challenge'])) {
				return $challenge;
			} else {
				throw new \Exception('Unable to get a user-challenge; please try again or check your Discourse install.');
			}
		} catch (\Exception $e) {
			throw new \Exception('Unable to get a user-challenge; please try again or check your Discourse install. (Network)');
		}
	}

	// Thread and modifications
	public function createTopic($title, $text, $categoryId, $reply = 0)
	{
		$data = [
			'body' => [
				'title' => $title,
				'raw' => $text,
				'category' =>  $categoryId,
				'archetype' => 'regular',
				'reply_to_post_number' => $reply,
			]
		];

		return $this->request('POST', '/posts', $data);
	}

	public function createPost($topicId, $text, $categoryId, $linkedTopic = 0)
	{
		$data = [
			'body' => [
				'topic_id' => (int) $topicId,
				'raw' => $text,
				'category' => (int) $categoryId,
				'archetype' => 'regular',
				'reply_to_post_number' => (int) $linkedTopic,
				'nested_post' => true,
			]
		];

		return $this->request('POST', '/posts', $data);
	}

	public function like($postId)
	{
		$postId = (int) $postId;
		if ($postId === 0 || empty($postId)) {
			throw new \Exception('A valid post ID is required.');
		}
		$data = [ 'body' => [ 'id' => (int) $postId, 'post_action_type_id' => 2, 'flag_topic' => false ], 'is_json' => false ];
		$req = $this->request('POST', '/post_actions', $data);

		if ($req->getStatusCode() === 200) {
			return ['success' => true, 'liked_post_id' => $postId];
		} else {
			return ['success' => false];
		}
	}

	public function deletePost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 0 || empty($postId)) {
			throw new \Exception('A valid post ID is required.');
		}

		return $this->request('DELETE', "/posts/$postId");
	}

	public function deleteTopic($topicId)
	{
		$topicId = (int) $topicId;
		if ($topicId === 0 || empty($topicId)) {
			throw new \Exception('A valid post ID is required.');
		}

		return $this->request('DELETE', "/t/$topicId");
	}

	public function recoverPost($postId)
	{
		$postId = (int) $postId;
		if ($postId === 0 || empty($postId)) {
			throw new \Exception('A valid post ID is required.');
		}

		return $this->request('PUT', "/posts/$postId/recover");
	}

	public function recoverTopic($topicId)
	{
		$topicId = (int) $topicId;
		if ($topicId === 0 || empty($topicId)) {
			throw new \Exception('A valid post ID is required.');
		}

		return $this->request('PUT', "/t/$topicId/recover");
	}

	// Group (requires user ID, NOT name)
	public function removeUserFromGroup($userId, $groupId)
	{
		$groupId = (int) $groupId;
		$userId = (int) $userId;
		if ($userId === 0 || empty($userId)) {
			throw new \Exception('A valid user ID is required (not username!).');
		}
		if ($groupId === 0 || empty($groupId)) {
			throw new \Exception('A valid group ID is required.');
		}

		$req = $this->request('DELETE', "/admin/users/$userId/groups/$groupId", ['is_json' => false]);
		if ($req->getStatusCode() === 200) {
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function addUserToGroup($userId, $groupId)
	{
		$groupId = (int) $groupId;
		$userId = (int) $userId;
		if ($userId === 0 || empty($userId)) {
			throw new \Exception('A valid user ID is required (not username!).');
		}
		if ($groupId === 0 || empty($groupId)) {
			throw new \Exception('A valid group ID is required.');
		}

		return $this->request('POST', "/admin/users/$userId/groups", [ 'body' => [ 'group_id' => $groupId ] ]);
	}

	public function setUserPrimaryGroup($userId, $groupId)
	{
		$groupId = (int) $groupId;
		$userId = (int) $userId;
		if ($userId === 0 || empty($userId)) {
			throw new \Exception('A valid user ID is required (not username!).');
		}
		if ($groupId === 0 || empty($groupId)) {
			throw new \Exception('A valid group ID is required.');
		}

		$req = $this->request('PUT', "/admin/users/$userId/primary_group", [ 'body' => [ 'primary_group_id' => $groupId ] , 'is_json' => false ]);
		if ($req->getStatusCode() === 200) {
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	// User
	public function getUserByUsername($username, $stats = false)
	{
		$stats = (bool) $stats;
		if ($username === 0 || empty($username)) {
			$username = $this->username;
		}

		return $this->request( 'GET', "/users/$username.json", [ 'query' => [ 'stats' => $stats ] ] );
	}

	public function getUserByEmail($email, $stats = false)
	{
		$users = $this->request( 'GET', '/admin/users/list/active.json', [ 'query' => ['filter' => $email, 'show_emails' => 'true' ] ]);

		foreach($users as $user) {
			if($user->email === $email) {
				return $user;
			}
		}
		return false;
	}

	public function getUserBadges($username, $group = false)
	{
		$group = (bool) $group;
		if ($username === 0 || empty($username)) {
			$username = $this->username;
		}

		return $this->request('GET', "/user-badges/$username.json?grouped=$group");
	}

	public function createUser($userName, $password, $email, $fullName = '')
	{
		$challenge = $this->challenge();
		$user = [
			'form_params' => [
				'username' => $userName,
				'email' => $email,
				'password' => $password,
				'password_confirmation' => $challenge['value'],
				'challenge' => strrev($challenge['challenge']),
				'name' => $fullName,
			],
		];

		return $this->request('POST', '/users', $user);
	}

	public function updateUser( $username, $data )
	{
		return $this->request( 'PUT', "/users/$username", [ 'form_params' => $data ] );
	}

	public function deleteUser($uid)
	{
		$uid = (int) $uid;
		if ($uid === 0 || empty($uid)) {
			throw new \Exception('A valid user ID is required, you can use getUserByUsername(username).');
		}

		return $this->request('DELETE', "/admin/users/$uid.json");
	}

	public function anonymizeUser($uid)
	{
		$uid = (int) $uid;
		if ($uid === 0 || empty($uid)) {
			throw new \Exception('A valid user ID is required, you can use getUserByUsername(username).');
		}

		return $this->request('PUT', "/admin/users/$uid/anonymize.json");
	}

	// Generics
	public function getAbout()
	{
		return $this->request('GET', '/about.json');
	}

	// Admin
	public function getFlags($type = 'active', $offset = 0)
	{
		$valid_types = ['old', 'active'];
		if (!in_array($type, $valid_types)) {
			throw new \Exception('Flags must be one of: '.implode(', ', $valid_types));
		}
		$offset = (int) $offset;
		return $this->request('GET', "/admin/flags/$type.json?offset=$offset");
	}

	/*???? FTW */
	public function setSetting($key, $value)
	{
		return $this->request('PUT', "/admin/users/$key", [ "form_params" => [$key => $value] ]);
	}

	// Forum
	public function getCategories()
	{
		return $this->request('GET', '/categories.json');
	}

	public function getCategory($slug, $sort = 'latest', $page = 0)
	{
		$valid_sorts = ['latest', 'new', 'unread', 'top'];
		if (!in_array($sort, $valid_sorts)) {
			throw new \Exception('Sort must be one of: '.implode(', ', $valid_sorts));
		}
		$page = (int) $page;
		return $this->request('GET', "/c/$slug/l/$sort.json?page=$page");
	}

	public function getPost($topicId, $postId = 1)
	{
		$topicId = (int) $topicId;
		$postId = (int) $postId;
		if ($topicId === 0 || empty($topicId)) {
			throw new \Exception('A valid topic ID is required.');
		}

		return $this->request('GET', "/posts/by_number/$topicId/$postId.json");
	}
	public function getTopic($topicId)
	{
		$topicId = (int) $topicId;
		if ($topicId === 0 || empty($topicId)) {
			throw new \Exception('A valid topic ID is required.');
		}

		return $this->request('GET', "/t/$topicId.json");
	}

	// Search
	public function searchInForum($query, $context = false)
	{
		$context = (bool) $context;

		return $this->request('GET', "/search/query.json?term=$query&include_blurbs=$context");
	}

	public function searchInTopic($query, $context, $topicId)
	{
		$context = (bool) $context;
		$topicId = (int) $topicId;
		if ($topicId === 0 || empty($topicId)) {
			throw new \Exception('A valid topic ID is required.');
		}

		return $this->request('GET', "/search/query.json?term=$query&include_blurbs=$context&search_context[type]=topic&search_context[id]=$topicId");
	}

	public function searchInCategory($query, $context, $categoryId)
	{
		$context = (bool) $context;
		$categoryId = (int) $categoryId;
		if ($categoryId === 0 || empty($categoryId)) {
			throw new \Exception('A valid category ID is required.');
		}

		return $this->request('GET', "/search/query.json?term=$query&include_blurbs=$context&search_context[type]=category&search_context[id]=$categoryId");
	}

	public function searchInUserPosts($query, $context, $username)
	{
		$context = (bool) $context;
		return $this->request('GET', "/search/query.json?term=$query&include_blurbs=$context&search_context[type]=user&search_context[id]=$username");
	}
}
