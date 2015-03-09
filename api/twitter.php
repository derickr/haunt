<?php
class drtFetcher
{
	public $username;
	protected $statusbar;


	function __construct( ezcDbHandler $d, $statusbar )
	{
		$this->d = $d;
		$this->statusbar = $statusbar;
		$this->reloadConfig();

		$ctx = stream_context_create( array( 'http' => array( 'timeout' => 10 ) ) );
		stream_context_set_default( array("notification" => array( $this, "stream_notification_callback" ) ) );
	}

	function reloadConfig()
	{
		$settings = $this->fetchConfigValues();
		$this->username = isset( $settings['username'] ) ? $settings['username'] : 'anonymous';
		$this->consumer_key = isset( $settings['consumerKey'] ) ? $settings['consumerKey'] : false;
		$this->consumer_secret = isset( $settings['consumerSecret'] ) ? $settings['consumerSecret'] : false;
		$this->oauth_token = isset( $settings['accessToken'] ) ? $settings['accessToken'] : false;
		$this->oauth_token_secret = isset( $settings['accessTokenSecret'] ) ? $settings['accessTokenSecret'] : false;
		$this->useGps = isset( $settings['useGps'] ) ? $settings['useGps'] : false;
		$this->useWlan = isset( $settings['useWlan'] ) ? $settings['useWlan'] : false;

		echo "Resetting OAuth\n";
		try
		{
			$this->oauth = new OAuth( $this->consumer_key, $this->consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI );
			$this->oauth->disableSSLChecks();
			$this->oauth->enableDebug();
		}
		catch ( Exception $e )
		{
		}
	}

	function fetchConfigValues()
	{
		$q = $this->d->createSelectQuery();
		$q->select( '*' )->from( 'settings' );
		$s = $q->prepare();
		$s->execute();

		$settings = array();
		foreach ( $s as $setting )
		{
			$settings[$setting['name']] = $setting['value'];
		}
		return $settings;
	}

	function saveSetting( $name, $value )
	{
		$q = $this->d->createUpdateQuery();
		$q->update( 'settings' )->set( 'value', $q->bindValue( $value ) )->where( $q->expr->eq( 'name', $q->bindValue( $name ) ) );
		$s = $q->prepare();
		$s->execute();
	}

	function updateGUI()
	{
		if ( !class_exists( 'gtk' ) ) 
		{
			return;
		}
        while(Gtk::events_pending() || Gdk::events_pending())
		{
            Gtk::main_iteration_do(true);
        } 
	}

	function setMessageLead( $msg )
	{
		$this->msgLead = $msg. ': ';
		$this->message( $msg . '.' );
		$this->updateGUI();
	}

	function message( $msg )
	{
		echo $msg, "\n";
		if ( !class_exists( 'gtk' ) ) 
		{
			return;
		}
		$this->statusbar->pop( 1 );
		$this->statusbar->push( 1, $msg );
		$this->updateGUI();
	}

	/**
	 * Fetches information from the database about the last fetched ID, the last
	 * viewed ID etc.
	 * 
	 * @return array(string=>int)
	 */
	function fetchStatus()
	{
	}

	function getMaxId( $type )
	{
		$q = $this->d->createSelectQuery();
		$q->select( $q->expr->max( 'id' ) )
		   ->from( 'status' )
		   ->where( $q->expr->eq( 'type', $q->bindValue( $type ) ) );
		$s = $q->prepare();
		$s->execute();
		$r = $s->fetchAll();
		return max( 1, $r[0][0] );
	}

	function addSearch( $term )
	{
		$q = $this->d->createInsertQuery();
		$q->insertInto( 'search' )
		  ->set( 'terms', $q->bindValue( $term ) );
		$s = $q->prepare();
		$s->execute();
	}

	function delSearch( $term )
	{
		$q = $this->d->createDeleteQuery();
		$q->deleteFrom( 'search' )
		  ->where( $q->expr->eq( 'terms', $q->bindValue( $term ) ) );
		$s = $q->prepare();
		$s->execute();
	}

	function fetchSearches()
	{
		$q = $this->d->createSelectQuery();
		$q->select( 'terms' )->from( 'search' );
		$q->orderBy( 'terms' );
		$s = $q->prepare();
		$s->execute();
		$searches = array();
		foreach ( $s as $res )
		{
			$searches[] = $res['terms'];
		}
		return $searches;
	}

	function addFilter( $term )
	{
		$q = $this->d->createInsertQuery();
		$q->insertInto( 'filter' )
		  ->set( 'terms', $q->bindValue( $term ) );
		$s = $q->prepare();
		$s->execute();
	}

	function delFilter( $term )
	{
		$q = $this->d->createDeleteQuery();
		$q->deleteFrom( 'filter' )
		  ->where( $q->expr->eq( 'terms', $q->bindValue( $term ) ) );
		$s = $q->prepare();
		$s->execute();
	}

	function fetchFilters()
	{
		$q = $this->d->createSelectQuery();
		$q->select( 'terms' )->from( 'filter' );
		$q->orderBy( 'terms' );
		$s = $q->prepare();
		$s->execute();
		$filters = array();
		foreach ( $s as $res )
		{
			$filters[] = preg_quote( $res['terms'] );
		}
		return $filters;
	}

	function createUpdateOrInsert( $type, $id )
	{
		$q = $this->d->createSelectQuery();
		$q->select( '*' )->from( $type )
		  ->where( $q->expr->eq( 'id', $q->bindValue( $id ) ) );
		$s = $q->prepare();
		$s->execute();
		$r = $s->fetchAll();
		if ( count( $r ) > 0 )
		{
			$q = $this->d->createUpdateQuery();
			$q->update( $type );
			$q->where( $q->expr->eq( 'id', $q->bindValue( $id ) ) );
		}
		else
		{
			$q = $this->d->createInsertQuery();
			$q->insertInto( $type );
			$q->set( 'id', $q->bindValue( $id ) );
		}
		return $q;
	}

	function parseFriend( $json )
	{
		$q = $this->createUpdateOrInsert( 'friend', (int) $json->id );
		$q->set( 'name', $q->bindValue( (string) $json->name ) );
		$q->set( 'screen_name', $q->bindValue( (string) $json->screen_name ) );
		$s = $q->prepare();
		$s->execute();
	}

	function parseUser( $json )
	{
		$q = $this->createUpdateOrInsert( 'user', (int) $json->id );
		$q->set( 'name', $q->bindValue( (string) $json->name ) );
		$q->set( 'screen_name', $q->bindValue( (string) $json->screen_name ) );
		$q->set( 'location', $q->bindValue( (string) $json->location ) );
		$q->set( 'profile_image_url', $q->bindValue( (string) $json->profile_image_url ) );
		$q->set( 'url', $q->bindValue( (string) $json->url ) );
		$q->set( 'utc_offset', $q->bindValue( (string) $json->utc_offset ) );
		$q->set( 'timezone', $q->bindValue( (string) $json->time_zone ) );
		$s = $q->prepare();
		$s->execute();
	}

	function fixURLs( $text, $urls )
	{
		foreach ( $urls as $url )
		{
			if ($url->expanded_url && $url->url) {
				$text = str_replace( $url->url, $url->expanded_url, $text );
			}
		}
		return $text;
	}

	function parseStatus( $json )
	{
		$q = $this->createUpdateOrInsert( 'status', $json->id );
		$q->set( 'type', $q->bindValue( 'public' ) );
		$q->set( 'time', $q->bindValue( strtotime( $json->created_at ) ) );
		$q->set( 'text', $q->bindValue( $this->fixURLs( (string) $json->text, $json->entities->urls) ) );
		$q->set( 'source', $q->bindValue( (string) $json->source ) );
		$q->set( 'in_reply_to_status', $q->bindValue( $json->in_reply_to_status_id ) );
		$q->set( 'in_reply_to_user', $q->bindValue( (int) $json->in_reply_to_user_id ) );
		$q->set( 'user_id', $q->bindValue( (int) $json->user->id ) );
		$s = $q->prepare();
		$s->execute();
	}

	function parseDirectMessage( $json )
	{
		$q = $this->createUpdateOrInsert( 'status', $json->id );
		$q->set( 'type', $q->bindValue( 'direct' ) );
		$q->set( 'time', $q->bindValue( strtotime( $json->created_at ) ) );
		$q->set( 'text', $q->bindValue( $this->fixURLs( (string) $json->text, $json->entities->urls) ) );
		$q->set( 'in_reply_to_user', $q->bindValue( $json->recipient->id ) );
		$q->set( 'user_id', $q->bindValue( (int) $json->sender->id ) );
		$s = $q->prepare();
		$s->execute();
	}

	function parseSentDirectMessage( $json )
	{
		$q = $this->createUpdateOrInsert( 'status', $json->id );
		$q->set( 'type', $q->bindValue( 'direct-sent' ) );
		$q->set( 'time', $q->bindValue( strtotime( $json->created_at ) ) );
		$q->set( 'text', $q->bindValue( $this->fixURLs( (string) $json->text, $json->entities->urls) ) );
		$q->set( 'in_reply_to_user', $q->bindValue( $json->sender->id ) );
		$q->set( 'user_id', $q->bindValue( (int) $json->recipient->id ) );
		$s = $q->prepare();
		$s->execute();
	}

	function parseTimeline( $json )
	{
		$this->message( 'Processing timeline.' );
		$count = 0;
		foreach( $json as $status )
		{
			$this->parseStatus( $status );
			$this->parseUser( $status->user );
			$count++;
			$this->message( 'Processing timeline: ' . $count );
		}
		return $count;
	}

	function parseDirectMessages( $json, $sent = false )
	{
		$this->message( 'Processing direct messages.' );
		$count = 0;
		foreach( $json as $status )
		{
			$this->parseDirectMessage( $status, $sent );
			$this->parseUser( $status->sender );
			$count++;
			$this->message( 'Processing direct messages: ' . $count );
		}
		return $count;
	}

	function parseSentDirectMessages( $json )
	{
		$this->message( 'Processing sent-direct messages.' );
		$count = 0;
		foreach( $json as $status )
		{
			$this->parseSentDirectMessage( $status );
			$this->parseUser( $status->sender );
			$count++;
			$this->message( 'Processing sent-direct messages: ' . $count );
		}
		return $count;
	}

	function parseFriends( $json )
	{
		$this->message( 'Processing friends.' );
		$count = 0;
		foreach( $json as $user )
		{
			$this->parseFriend( $user );
			$this->parseUser( $user );
			$count++;
			$this->message( 'Processing friends: ' . $count );
		}
		return $count;
	}

	function fetchProfileImages()
	{
		$q = $this->d->createSelectQuery();
		$q->select( 'name, profile_image_url' )->from( 'user' );
		$s = $q->prepare();
		$s->execute();
		foreach ( $s as $r )
		{
			$hash = md5( $r['profile_image_url'] );

			$q2 = $this->d->createSelectQuery();
			$q2->select( 'id' )->from( 'image' )
			  ->where( $q2->expr->eq( 'id', $q2->bindValue( $hash ) ) );
			$s2 = $q2->prepare();
			$s2->execute();
			$r2 = $s2->fetchAll();
			if ( count( $r2 ) == 0 )
			{
				$this->setMessageLead( "Fetching profile image for {$r['name']}" );
				$imageData = base64_encode( $this->fetchFromUrl( $r['profile_image_url'] ) );
				$iq = $this->d->createInsertQuery();
				$iq->insertInto( 'image' )
				   ->set( 'id', $iq->bindValue( $hash ) )
				   ->set( 'image', $iq->bindValue( $imageData ) );
				$is = $iq->prepare();
				$is->execute();
			}
			$this->updateGUI();
		}
	}

	function stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max)
	{
		switch($notification_code) {
			case STREAM_NOTIFY_RESOLVE:
			case STREAM_NOTIFY_AUTH_REQUIRED:
			case STREAM_NOTIFY_FAILURE:
			case STREAM_NOTIFY_AUTH_RESULT:
			case STREAM_NOTIFY_MIME_TYPE_IS:
				/* Ignore */
				break;

			case STREAM_NOTIFY_REDIRECTED:
				$this->message( $this->msgLead . "Being redirected to: ", $message );
				break;

			case STREAM_NOTIFY_CONNECT:
				$this->message( $this->msgLead . "Connected." );
				break;

			case STREAM_NOTIFY_PROGRESS:
				$this->message( $this->msgLead . "Transferred: " . sprintf( '%.1f kb', $bytes_transferred / 1024 ) );
				break;

			case STREAM_NOTIFY_COMPLETED:
				$this->message( $this->msgLead . "Completed." );
		}
	}


	function fetchFromUrl( $url )
	{
		$ctx = stream_context_create( array( 'http' => array( 'timeout' => 10, 'useragent' => 'Haunt - http://derickrethans.nl/projects.html#haunt' ) ) );
		stream_context_set_params( $ctx, array("notification" => array( $this, "stream_notification_callback" ) ) );
		return file_get_contents( $url, null, $ctx );
	}

	function doOAuth( $url, array $params, $method = OAUTH_HTTP_METHOD_GET )
	{
		try
		{
			$this->oauth->setToken( $this->oauth_token, $this->oauth_token_secret );
			$this->oauth->fetch( $url, $params, $method, array( "User-Agent" => "Haunt" ) );
		}
		catch( OAuthException $e )
		{
			var_dump( $e );
			echo $this->oauth->getLastResponse(), "\n";
		}

		return $this->oauth->getLastResponse();
	}

	function post( $message, $replyToID = false, $coords = false )
	{
		$contentArray = array();
		$contentArray['status'] = $message;
		if ( $replyToID )
		{
			$contentArray['in_reply_to_status_id'] = $replyToID;
		}
		if ( $coords )
		{
			$contentArray['lat'] =  $coords[2];
			$contentArray['long'] = $coords[3];
		}

		$string = $this->doOAuth( 'https://api.twitter.com/1.1/statuses/update.json', $contentArray, OAUTH_HTTP_METHOD_POST );

		return json_decode( $string );
	}

	function fetchXmlString( $url )
	{
		$url = "http://{$this->username}:{$this->password}@twitter.com/" . $url;
		return simplexml_load_string( $this->fetchFromUrl( $url ) );
	}

	function fetchJsonString( $url, array $params )
	{
		$ret = $this->doOAuth( $url, $params );
		return json_decode( $ret );
	}

	function fetchSearchString( $search, $max )
	{
		$url = "https://api.twitter.com/1.1/search/tweets.json?";
		$json = $this->fetchJsonString( $url, array( 'since_id' => $max, 'count' => 100, 'q' => urlencode( $search ) ) );
		return $json;
	}

	function canCall()
	{
		$json = $this->fetchJsonString( "http://api.twitter.com/1.1/application/rate_limit_status.json", array() );
		return ( $json->{'remaining_hits'} > 5 );
	}

	function fetchAllFriends()
	{
		$page = 1;
		do
		{
			$url = 'http://api.twitter.com/1.1/statuses/friends.json';
			$json = $this->fetchJsonString( $url, array( 'page' => $page ) );
			$newFriends = $this->parseFriends( $json );
			$page++;
		} while ( $newFriends > 0 && $this->canCall() );
	}

	function fetchUserIdByScreenName( $screenName )
	{
		$this->setMessageLead( "Fetching user details for '$screenName'" );
		$url = 'http://api.twitter.com/1.1/users/show.json';
		$params = array( 'screen_name' => urlencode( $screenName ) );
		$json = $this->fetchJsonString( $url, $params );
		$this->parseUser( $json );

		return $json->id;
	}

	function parseSearchResult( $result )
	{
		$id_str = (string) $result->id_str;
		$q = $this->createUpdateOrInsert( 'status', $id_str );
		$q->set( 'user_id', $q->bindValue( $result->user->id ) );
		$q->set( 'type', $q->bindValue( 'search' ) );
		$q->set( 'time', $q->bindValue( strtotime( $result->created_at ) ) );
		$q->set( 'text', $q->bindValue( (string) $result->text ) );
		$q->set( 'source', $q->bindValue( (string) $result->source ) );
		$q->set( 'in_reply_to_user', $q->bindValue( (int) $result->in_reply_to_user_id ) );
		$s = $q->prepare();

		$s->execute();
	}

	function parseSearchResults( $json )
	{
		$count = 0;
		foreach ( $json->statuses as $result )
		{
			$this->parseSearchResult( $result );
			$this->parseUser( $result->user );
		}
		return $count;
	}

	function fetchNewStatuses()
	{
		$total = 0;

		$this->d->beginTransaction();

		$max = $this->getMaxId( 'search' );
		$searches = $this->fetchSearches();
		foreach( $searches as $search )
		{
			$this->setMessageLead( "Fetching search results for '$search'" );
			$page = 1;
			do
			{
				$json = $this->fetchSearchString( $search, $max );
				$newStatuses = $this->parseSearchResults( $json );
				$page++;
				$total += $newStatuses;
			} while ( $newStatuses >= 190 && $this->canCall() );
		}

		$this->setMessageLead( "Fetching tweets" );
		$page = 1;
		$max = $this->getMaxId( 'public' );
		do
		{
			$url = "https://api.twitter.com/1.1/statuses/home_timeline.json";
			$json = $this->fetchJsonString( $url, array( 'since_id' => $max, 'count' => 200, 'page' => $page, 'include_entities' => 1 ) );
			$newStatuses = $this->parseTimeline( $json );
			$page++;
			$total += $newStatuses;
		} while ( $newStatuses >= 190 && $this->canCall() );

		$this->setMessageLead( "Fetching direct messages" );
		$page = 1;
		$max = $this->getMaxId( 'direct' );
		do
		{
			$url = "https://api.twitter.com/1.1/direct_messages.json";
			$json = $this->fetchJsonString( $url, array( 'since_id' => $max, 'count' => 200, 'page' => $page, 'include_entities' => 1 ) );
			$newStatuses = $this->parseDirectMessages( $json );
			$page++;
			$total += $newStatuses;
		} while ( $newStatuses >= 190 && $this->canCall() );

		$this->setMessageLead( "Fetching sent-direct messages" );
		$page = 1;
		$max = $this->getMaxId( 'direct-sent' );
		do
		{
			$url = "https://api.twitter.com/1.1/direct_messages/sent.json";
			$json = $this->fetchJsonString( $url, array( 'since_id' => $max, 'count' => 200, 'page' => $page, 'include_entities' => 1 ) );
			$newStatuses = $this->parseSentDirectMessages( $json );
			$page++;
			$total += $newStatuses;
		} while ( $newStatuses >= 190 && $this->canCall() );

		$this->setMessageLead( 'Fetching done' );
		$this->d->commit();

		return $total;
	}
}
?>
