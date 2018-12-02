<?php
class Twitter extends GtkWindow
{
	protected $statusicon;
	protected $twitter;
	protected $treeview;
	protected $statusbar;
	protected $public_timeline_timeout;
	protected $message_renderer = NULL;
	protected $settings;
	private   $drag = false;
	private   $coords = array();
	private   $searchTerm = '';
	private   $searchMode = false;

	function foo( $a, $v )
	{
		if ( $this->message_renderer )
		{
			$width = function_exists( 'imagecreatefromstring' ) ? 112 : 60;
			$this->message_renderer->set_property('wrap-width', $v->width - $width);
			$this->message_renderer->set_property('width', $v->width - $width);
		}
	}

	function destroy()
	{
		gtk::main_quit();
		die();
	}

	public function __construct() 
	{
		parent::__construct();
		$accels = new GtkAccelGroup();
		$this->add_accel_group( $accels );

		$this->set_icon($this->render_icon(Gtk::STOCK_ABOUT, Gtk::ICON_SIZE_DIALOG));
		$this->set_size_request(800, 600);
		$this->move(1108, 398);
		$this->set_title('Twitter Client');
		$this->connect('destroy', array( $this, 'destroy' ) );
		$this->connect('size-allocate', array( $this, 'foo' ));

		$this->statusbar = new GtkStatusBar();

		$this->twitter = new drtFetcher( $GLOBALS['d'], $this->statusbar );

		$tb = new GtkToolbar();
		$tb->set_show_arrow(false);
		$tb->set_property( 'toolbar-style', Gtk::TOOLBAR_BOTH );
		$tb->set_property( 'icon-size', 6 );
		$this->updatebutton = GtkToolButton::new_from_stock(Gtk::STOCK_REFRESH);
		$lbl = new GtkLabel();
		$lbl->set_markup( '<span underline="single">U</span>pdate' );
		$this->updatebutton->set_label_widget( $lbl );
		$this->updatebutton->connect_simple('clicked', array($this, 'update'));
		$this->updatebutton->add_accelerator( 'clicked', $accels, Gdk::KEY_U, Gdk::MOD1_MASK, 0 );
		$this->updatebutton->set_use_underline( true );
		$tb->insert($this->updatebutton, -1);

		$this->replybutton = GtkToolButton::new_from_stock(Gtk::STOCK_OK);
		$lbl = new GtkLabel();
		$lbl->set_markup( '<span underline="single">R</span>eply' );
		$this->replybutton->set_label_widget( $lbl );
		$this->replybutton->connect_simple('clicked', array($this, 'reply'));
		$this->replybutton->add_accelerator( 'clicked', $accels, Gdk::KEY_R, Gdk::MOD1_MASK, 0 );
		$tb->insert($this->replybutton, -1);

		$this->retweetbutton = GtkToolButton::new_from_stock(Gtk::STOCK_CONVERT);
		$lbl = new GtkLabel();
		$lbl->set_markup( 'Re<span underline="single">t</span>weet' );
		$this->retweetbutton->set_label_widget( $lbl );
		$this->retweetbutton->connect_simple('clicked', array($this, 'retweet'));
		$this->retweetbutton->add_accelerator( 'clicked', $accels, Gdk::KEY_T, Gdk::MOD1_MASK, 0 );
		$tb->insert($this->retweetbutton, -1);

		$translatebutton = GtkToolButton::new_from_stock( Gtk::STOCK_UNDERLINE );
		$lbl = new GtkLabel();
		$lbl->set_markup( 'Tr<span underline="single">a</span>nslate' );
		$translatebutton->set_label_widget( $lbl );
		$translatebutton->connect_simple( 'clicked', array( $this, 'translate' ) );
		$translatebutton->add_accelerator( 'clicked', $accels, Gdk::KEY_A, Gdk::MOD1_MASK, 0 );
		$tb->insert( $translatebutton, -1 );

		$settingsbutton = GtkToolButton::new_from_stock( Gtk::STOCK_PROPERTIES );
		$lbl = new GtkLabel();
		$lbl->set_markup( '<span underline="single">S</span>ettings' );
		$settingsbutton->set_label_widget( $lbl );
		$settingsbutton->connect_simple( 'clicked', array( $this, 'showSettings' ) );
		$settingsbutton->add_accelerator( 'clicked', $accels, Gdk::KEY_S, Gdk::MOD1_MASK, 0 );
		$tb->insert( $settingsbutton, -1 );

		$this->connect('key-press-event', array( $this, 'onKeyPress' ) );

		// Create an update area
		$this->updateentry = new GtkEntry();
		$this->updateentry->set_max_length(140);
		$this->updateentry->set_sensitive(true);
		$this->updateentry->connect('activate', array($this, 'newtweet'));

		// User image pixbuf, user image string, user name, display name, user id, text, favorited, created_at, id, color, read, type
		$store = new GtkListStore(GdkPixbuf::gtype, Gobject::TYPE_STRING, Gobject::TYPE_STRING, Gobject::TYPE_STRING,
			Gobject::TYPE_STRING, GObject::TYPE_STRING, GObject::TYPE_BOOLEAN, GObject::TYPE_STRING,
			Gobject::TYPE_STRING, GObject::TYPE_STRING, GObject::TYPE_BOOLEAN, GObject::TYPE_STRING );
		$store->set_sort_column_id(6, Gtk::SORT_DESCENDING);

		// stuff vbox
		$vbox = new GtkVBox();
		$this->add($vbox);
		$vbox->pack_start($tb, false, false);
		$scrolled = new GtkScrolledWindow();
		$scrolled->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_ALWAYS);
		$vbox->pack_start($scrolled);
		$this->treeview = new GtkTreeView($store);
		$scrolled->add($this->treeview);
		$this->treeview->set_property('headers-visible', false);
		$this->treeview->set_rules_hint(true);

		$this->treeview->set_events(Gdk::BUTTON_PRESS_MASK);
		$this->treeview->connect('button-press-event', array( $this, 'onButtonPress' ) );
		$this->treeview->connect('button-release-event', array( $this, 'onButtonRelease') );
		$this->treeview->connect('motion-notify-event', array( $this, 'onDrag') );

		$ubox = new GtkHBox();
		$vbox->pack_start($ubox, false, false);

		$this->operationLabel = new GtkLabel();
		$this->operationLabel->set_markup_with_mnemonic( '<span underline="single">N</span>ew: ' );
		$ubox->pack_start($this->operationLabel, false, false);
		$ubox->pack_start($this->updateentry, true, true);
		$this->newtweetbutton = GtkToolButton::new_from_stock(Gtk::STOCK_ADD);
		$this->newtweetbutton->connect_simple('clicked', array($this, 'newtweet'));
		$this->newtweetbutton->add_accelerator( 'clicked', $accels, Gdk::KEY_Return, Gdk::MOD1_MASK, 0 );
		$ubox->pack_start($this->newtweetbutton, false, false);

		$vbox->pack_start($this->statusbar, false, false);
		$this->update_timeline();
		$this->statusbar->push(1, 'last updated ' . date('Y-m-d H:i') );

		$picture_renderer = new GtkCellRendererPixbuf();
		$picture_renderer->set_property('yalign', 0);
		$picture_column = new GtkTreeViewColumn('Picture', $picture_renderer, 'pixbuf', 0, 'cell-background', 9 );
		$this->treeview->append_column($picture_column);

		$width = function_exists( 'imagecreatefromstring' ) ? 112 : 60;
		$this->message_renderer = new GtkCellRendererText();
		$this->message_renderer->set_property('wrap-mode', Gtk::WRAP_WORD);
		$this->message_renderer->set_property('wrap-width', 480 - 60 - $width);
		$this->message_renderer->set_property('width', 480 - 60 - $width);

		$message_column = new GtkTreeViewColumn('Message', $this->message_renderer, 'text', 4, 'background', 9 );
		$message_column->set_cell_data_func($this->message_renderer, array($this, 'message_markup'));
		$this->treeview->append_column($message_column);

		$this->treeview->set_resize_mode(Gtk::RESIZE_QUEUE);

		$this->settings = new TwitterSettingsWindow( $this->twitter );

		Gtk::timeout_add( 60000, array( $this, 'checkGps' ) );
	}

	function setEmptyTweetBox()
	{
		$this->updateentry->grab_focus();
		$this->updateentry->set_text( '' );
		$this->replyToID = false;
	}
 
	//here we handle the key press events
	function onKeyPress($widget, $event)
	{
		if ($event->state & Gdk::MOD1_MASK && $event->keyval == Gdk::KEY_q) {
			$this->destroy();
		}
		if ($event->state & Gdk::MOD1_MASK && $event->keyval == Gdk::KEY_n) {
			$this->setEmptyTweetBox();
		}
		if ($event->state & Gdk::MOD1_MASK && $event->keyval == Gdk::KEY_0) {
			$this->doOpenTweetUrl( $event->keyval );
		}
		if ($event->state & Gdk::MOD1_MASK && in_array($event->keyval, array(Gdk::KEY_1, Gdk::KEY_2, Gdk::KEY_3))) {
			$this->doUrl( $event->keyval );
		}
		if ($event->state & Gdk::MOD1_MASK && $event->keyval == Gdk::KEY_f)
		{
			if ($this->searchMode)
			{
				$this->searchMode = false;
				$this->operationLabel->set_markup_with_mnemonic( '<span underline="single">N</span>ew: ' );
			}
			else
			{
				$this->searchMode = true;
				$this->operationLabel->set_markup_with_mnemonic( '<span underline="single">F</span>ind: ' );
			}
			$this->setEmptyTweetBox();
		}
	}

	//here we handle the mouse button press events
	function onButtonPress($widget, $event) 
	{
		$this->drag = $event->y;
	}

	function onButtonRelease($widget, $event)
	{
		$this->drag = false;
	}

	function onDrag($widget, $event)
	{
		if ( $this->drag )
		{
			$a = $widget->get_vadjustment();
			$old = $a->get_value();
			$new = $old + (($this->drag - $event->y) * 2);
			if ( abs( $new - $old ) > 25 )
			{
				$a->set_value( $new );
				$this->drag = $event->y;
				$widget->set_vadjustment( $a );
			}
		}
	}

	public function reply()
	{
		// find which entry is selected
		$treeview = $this->treeview;
		$selection = $treeview->get_selection();
		list($model, $iter) = $selection->get_selected();
		if ( !$iter )
		{
			return;
		}
		if ($model->get_value($iter, 11) == 'direct' )
		{
			$this->updateentry->set_text( 'd ' . $model->get_value($iter, 3 ). ' ' );
		}
		else if ($model->get_value($iter, 11) == 'direct-sent' )
		{
			$this->updateentry->set_text( 'd ' . $model->get_value($iter, 3 ). ' ' );
		}
		else
		{
			$this->updateentry->set_text( '@' . $model->get_value($iter, 3 ). ': ' );
		}
		$this->updateentry->grab_focus();
		$this->updateentry->set_position( -1 );
		$this->replyToID = $model->get_value( $iter, 8 );
	}

	public function translate()
	{
		// find which entry is selected
		$treeview = $this->treeview;
		$selection = $treeview->get_selection();
		list($model, $iter) = $selection->get_selected();
		if ( !$iter )
		{
			$this->twitter->message( "No message selected" );
			return;
		}
		$original = $model->get_value( $iter, 5 );
		$this->twitter->setMessageLead( "Translating" );
		if ( preg_match( '@Translated from [a-z|]+ by Bing@i', $original ) )
		{
			$this->twitter->message( "It's already translated" );
			return;
		}
		$original = urlencode( $original );

		// detect language
		$language = (string) simplexml_load_string( $this->twitter->fetchFromUrl( "http://api.microsofttranslator.com/V2/Http.svc/Detect?appId=4D8331CECA81608E61E01498C32B1EF9358293D7&text=$original" ) );
		if ( $language == 'en' )
		{
			$this->twitter->message( "It's already in English" );
			return;
		}

		$translated = (string) simplexml_load_string( $this->twitter->fetchFromUrl( "http://api.microsofttranslator.com/V2/Http.svc/Translate?appId=4D8331CECA81608E61E01498C32B1EF9358293D7&to=en&text=$original" ) );
		$model->set($iter, 5, "{$translated} — Translated from {$language} by Bing" );
	}

	public function doOpenTweetUrl()
	{
		// find which entry is selected
		$treeview = $this->treeview;
		$selection = $treeview->get_selection();
		list($model, $iter) = $selection->get_selected();
		if ( !$iter )
		{
			$this->twitter->message( "No message selected" );
			return;
		}
		$user = $model->get_value( $iter, 3 );
		$id = $model->get_value( $iter, 8 );

		$link = "https://twitter.com/{$user}/status/{$id}";

		$this->twitter->message( "Opening URL '$link'" );
		passthru("firefox '$link'");
	}

	public function doUrl($keyval)
	{
		// find which entry is selected
		$treeview = $this->treeview;
		$selection = $treeview->get_selection();
		list($model, $iter) = $selection->get_selected();
		if ( !$iter )
		{
			$this->twitter->message( "No message selected" );
			return;
		}
		$text = $model->get_value( $iter, 5 );
		$pattern = '@(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))@';
		preg_match_all($pattern, $text, $m);

		if (isset($m[0][$keyval - Gdk::KEY_1])) {
			$link = $m[0][$keyval - Gdk::KEY_1];
			$this->twitter->message( "Opening URL '$link'" );
			passthru("firefox '$link'");
		}
	}

	public function retweet()
	{
		// find which entry is selected
		$treeview = $this->treeview;
		$selection = $treeview->get_selection();
		list($model, $iter) = $selection->get_selected();
		if ( !$iter )
		{
			return;
		}
		$this->updateentry->set_text( "RT @" . $model->get_value($iter, 3 ). ': ' . $model->get_value($iter, 5) );
		$this->updateentry->grab_focus();
		$this->updateentry->set_position( -1 );
	}

	public function checkGps()
	{
		$this->coords = null;
		if ( !$GLOBALS['gpsPos'] )
		{
			return true;
		}
		if ( $this->twitter->useGps )
		{
			try
			{
				$this->coords = $GLOBALS['gpsPos']->GetPosition()->getData();
				$this->fix    = $GLOBALS['gpsDevice']->GetFixStatus();
			}
			catch ( Exception $e )
			{
				$this->coords = null;
			}
		}
		else
		{
			$this->fix = -1;
		}

		switch ( $this->fix )
		{
			case -1: $txt = 'Disabled'; break;
			case 0: $txt = 'Invalid fix'; break;
			case 1: $txt = 'No fix'; break;
			case 2: $txt = '2D fix'; break;
			case 3: $txt = '3D fix'; break;
		}

		$time = date_format( date_create(), 'H:i' );
		if ( $this->coords )
		{
			$this->twitter->message( sprintf( "%s | GPS: %.2f, %.2f | %s", $time, $this->coords[2], $this->coords[3], $txt ) );
		}
		else if ( $this->fix == -1 )
		{
			if ( $this->twitter->useWlan )
			{
				$this->twitter->message( sprintf( "%s | GPS: disabled | WLAN: enabled", $time ) );
			}
			else
			{
				$this->twitter->message( sprintf( "%s | GPS: disabled | WLAN: disabled", $time ) );
			}
		}
		else
		{
			$this->twitter->message( sprintf( "%s | GPS: unknown | %s", $time, $txt ) );
		}
		return true;
	}

	public function checkWlanLocation()
	{
		$this->coords = null;
		if ( false /* $this->twitter->useWlan */ )
		{
			$this->locationInfo = $GLOBALS['wlanPos']->getPosition();
			$this->coords = array( 0, 0, $this->locationInfo->latitude, $this->locationInfo->longitude );
		}
		$txt = '';

		$time = date_format( date_create(), 'H:i' );
		if ( $this->coords )
		{
			$this->twitter->message( sprintf( "%s | WLAN: %.2f, %.2f", $time, $this->coords[2], $this->coords[3] ) );
		}
		else
		{
			$this->twitter->message( sprintf( "%s | WLAN: unknown", $time ) );
		}
		return true;
	}

	public function newtweet()
	{
		if ( $this->searchMode )
		{
			$this->twitter->message( 'Searching' );
			$this->searchTerm = $this->updateentry->get_text();
			$this->update_timeline();
			$this->twitter->message( 'Searching: done' );
			$this->searchMode = false;
			$this->operationLabel->set_markup_with_mnemonic( '<span underline="single">N</span>ew: ' );
			$this->setEmptyTweetBox();
			$this->treeview->grab_focus();
		}
		else
		{
			$this->twitter->message( 'Sending tweet' );
			$text = $this->updateentry->get_text();
			$text = trim( $text );
			if ( strlen( $text ) > 0 )
			{
				$this->checkWlanLocation();
				$this->twitter->post( $text, $this->replyToID, $this->coords );
				$this->replyToID = false;
				$this->updateentry->set_text( '' );
				$this->twitter->message( 'Sending tweet: Done.' );
				$this->treeview->grab_focus();
			}
		}
	}

	public function showSettings()
	{
		$this->settings->show_all();
	}

	public function message_markup($column, $cell, $store, $position)
	{
		$display_name = htmlspecialchars($store->get_value($position, 2));
		$type = ($store->get_value($position, 11));
		$user = ($store->get_value($position, 3));
		$message = ($store->get_value($position, 5));
		if ($type == 'direct-sent') {
			$display_name = 'To: ' . $display_name;
		}
		$time = $this->distance($store->get_value($position, 7));
		$formatted_time = date_create( $store->get_value($position, 7) )->format( 'H:i' );
		$message = htmlspecialchars( $message );
		$message = preg_replace( '/@([a-z0-9_]+)/i', '<i>@\\1</i>', $message );
		$markedUp = "<span><b>$display_name ($user)</b>:\n$message\n<small>$time - $formatted_time</small></span>";
		$markedUp = "<b>$display_name ($user)</b>:\n$message\n<small>$time - $formatted_time</small>";
		$cell->set_property('markup', $markedUp);
		$cell->set_property('foreground', '#000000');
		$cell->set_property('foreground-set', true);
	}

	protected function distance($from) {
		$minutes = round(abs(time() - strtotime( $from )) / 60);

		switch(true) {
			case ($minutes == 0):
				return 'less than 1 minute ago';
			case ($minutes < 1):
				return '1 minute ago';
			case ($minutes <= 55):
				return $minutes . ' minutes ago';
			case ($minutes <= 65):
				return 'about 1 hour ago';
			case ($minutes <= 1439):
				return 'about ' . round((float) $minutes / 60.0) . ' hours';
			case ($minutes <= 2879):
				return '1 day ago';
			case ($minutes <= 43200):
				return 'about ' . round((float) $minutes / 1440) . ' days ago - ' . date( 'M jS', strtotime( $from ) );
			case ($minutes <= 47520):
				return 'about a month ago - ' . date( 'M jS', strtotime( $from ) );
			default:
				return 'about ' . round((float) $minutes / (1440 * 30.5)) . ' months ago - ' . date( 'M jS, Y', strtotime( $from ) );
		}
	}

	protected function filtersMatch( $filters, $status )
	{
		if ( !$filters || count($filters) < 1 )
		{
			return false;
		}	
		$regexp = '/(' . join( ')|(', $filters ) . ')/i';
		return (bool) preg_match( $regexp, $status );
	}


	public function update_timeline()
	{
		// fetch filter words
		$filterWords = $this->twitter->fetchFilters();

		// check how many unread message we have
		$q = $GLOBALS['d']->createSelectQuery();
		$q->select( 'count(*) ')
		   ->from( 'status' )
		   ->where( $q->expr->isNull( 'read' ) );
		$s = $q->prepare();
		$s->execute();
		$r = $s->fetchAll();
		$unread = $r[0]['count(*)'];

		/* Fetch images for users that are returned here */
		if ( function_exists( 'imagecreatefromstring' ) )
		{
			// fetch the users for the last max(100, unread-messages) messages
			$fetchLimit = max(100, $unread);

			$q = $GLOBALS['d']->createSelectQuery();
			$q->select( 'status.user_id' )
			  ->from( 'status' )
			  ->leftJoin( 'user', 'status.user_id', 'user.id' )
			  ->where( $q->expr->not( $q->expr->isNull( 'status.id' ) ) );
			if ( $this->searchTerm && $this->searchTerm != "" )
			{
				$q->where( $q->expr->lOr(
					$q->expr->like( 'user.name', $q->bindValue( "%{$this->searchTerm}%" ) ),
					$q->expr->like( 'user.screen_name', $q->bindValue( "%{$this->searchTerm}%" ) ),
					$q->expr->like( 'status.text', $q->bindValue( "%{$this->searchTerm}%" ) )
				) );
			}
			$q->orderBy( 'status.time', ezcQuerySelect::DESC )->limit( $fetchLimit );
			$s = $q->prepare();
			$s->execute();

			$userIds = [];
			foreach ( $s as $object )
			{
				$userIds[] = $object['user_id'];
			}

			$this->twitter->fetchProfileImages( $userIds );
		}

		// fetch the last max(100, unread-messages) messages
		$fetchLimit = max(100, $unread);

		$q = $GLOBALS['d']->createSelectQuery();
		$q->select( 'status.*, user.name, user.screen_name, image.image' )
		  ->from( 'status' )
		  ->leftJoin( 'user', 'status.user_id', 'user.id' )
		  ->leftJoin( 'image', $q->expr->md5( 'user.profile_image_url'), 'image.id' )
		  ->where( $q->expr->not( $q->expr->isNull( 'status.id' ) ) );
		if ( $this->searchTerm && $this->searchTerm != "" )
		{
			$q->where( $q->expr->lOr(
				$q->expr->like( 'user.name', $q->bindValue( "%{$this->searchTerm}%" ) ),
				$q->expr->like( 'user.screen_name', $q->bindValue( "%{$this->searchTerm}%" ) ),
				$q->expr->like( 'status.text', $q->bindValue( "%{$this->searchTerm}%" ) )
			) );
			$this->searchTerm = '';
		}
		$q->orderBy( 'status.time', ezcQuerySelect::DESC )->limit( $fetchLimit );
		$s = $q->prepare();
		$s->execute();
		$store = $this->treeview->get_model();
		$store->clear();

		$firstNew = null;

		// User image pixbuf, user image string, user name, display_name, user id, text, favorited, created_at, id, color, read, type
		foreach ( $s as $object ) 
		{
			if ( $this->filtersMatch( $filterWords, $object['text'] ) )
			{
				continue;
			}
			if ( $object['time'] === '' )
			{
				continue;
			}
			$pb = null;
			if ( function_exists( 'imagecreatefromstring' ) && !empty( $object['image'] ) ) 
			{
				$i = imagecreatefromstring( base64_decode( $object['image'] ) );
				if (imagesx( $i) <= 48 && imagesy( $i ) <= 48 )
				{
					$pb = GdkPixbuf::new_from_gd( $i );
				}
			}
			$iter = $store->append(array(
				$pb, 'foo', $object['name'], $object['screen_name'],
				$object['user_id'],
				$object['text'], true, date( DateTime::RFC822,
				$object['time']), $object['id'], $this->getColor( $object),
				$object['read'], $object['type'] ) );

			if ( $object['read'] != 1 )
			{
				$firstNew = $store->get_path( $iter );
			}
		}

		if ( $firstNew !== NULL )
		{
			$this->treeview->set_cursor( $firstNew );
			$this->treeview->grab_focus();
			$this->treeview->scroll_to_cell( $firstNew, null, true, 0.9, 0 );
		}

		return true;
	}

	public function getColor( $object )
	{
		$r = $object['read'] == 0;
		if ( strstr( $object['text'], "@{$this->twitter->username}" ) !== false )
		{
			return $r ? "#ffff88" : "#ffffcc";
		}
		switch( $object['type'] )
		{
			case 'public': return $r ? "#ccccff" : "#8888cc";
			case 'direct': return $r ? "#ffcccc" : "#cc8888";
			case 'direct-new': return $r ? "#ffcccc" : "#cc8888";
			case 'direct-sent': return $r ? "#ffcccc" : "#cc8888";
			case 'search': return $r ? "#ccffcc" : "#66cc66";
		}
	}

	public function update()
	{
		global $d;

		$this->twitter->message( 'Marking old messages as read.' );
		$q = $d->createUpdateQuery();
		$q->update( 'status' )
		  ->set( 'read', $q->bindValue( 1 ) )
		  ->where( $q->expr->isNull( 'read' ) );
		$s = $q->prepare();
		$s->execute();

		$new = $this->twitter->fetchNewStatuses();
//		$this->twitter->fetchAllFriends();
		$this->twitter->message( 'Updating timeline' );

		$this->update_timeline();
		$this->statusbar->pop(1);
		$this->statusbar->push(1, 'last updated ' . date('Y-m-d H:i') . ' - ' . $new . ' new tweets');
		if ( $GLOBALS['soundDbus'] && $new > 1 )
		{
			try
			{
				$GLOBALS['soundDbus']->PlaySound( '/usr/share/sounds/notify_message.wav', 0, 0 );
			}
			catch ( Exception $e )
			{
				// ignore
			}
		}
	}

	public function login() {
		if (!empty($this->load_images_timeout)) {
			Gtk::timeout_remove($this->load_images_timeout);
			$readd = true;
		}
		Gtk::timeout_remove($this->public_timeline_timeout);
		$login = new Php_Gtk_Twitter_Login_Dialog($this);
		while($response = $login->run()) {
			if ($response == GTK::RESPONSE_CANCEL || $response == GTK::RESPONSE_DELETE_EVENT) {
				$this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_public_timeline')); // every 60 seconds
				$login->destroy();
				break;
			} elseif ($response == GTK::RESPONSE_OK) {
				if($login->check_login($this->twitter)) {
					$this->replybutton->set_sensitive(true);
					$this->updatebutton->set_sensitive(false);
					$login->destroy();
					$this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_timeline')); // every 60 seconds
					$this->treeview->get_model()->clear();
					$this->update_timeline();
					$this->updateentry->set_sensitive(true);
					break;
				}
			}
		}
	}

	public function logout() {
		$this->twitter->logout();
		$this->replybutton->set_sensitive(false);
		$this->updatebutton->set_sensitive(true);
		$this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_public_timeline')); // every 60 seconds
		$this->update_public_timeline();
		$this->updateentry->set_sensitive(false);
	}
}
?>
