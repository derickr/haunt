<?php
class TwitterSettingsWindow extends GtkWindow
{
	public $twitter = null;

	function __construct( $twitter )
	{
		parent::__construct();
		$this->set_size_request( 480, 600 );
		$this->set_title( 'Settings' );
		$this->connect_simple( 'show', array( $this, 'onShow' ) );

		$mainVBox = new GtkVBox();
		$this->add( $mainVBox );

		$tbl = new GtkTable( 8, 2, false );
		$mainVBox->pack_start( $tbl, false, false );

		$lbl = new GtkLabel( 'Username: ' );
		$tbl->attach( $lbl, 0, 1, 1, 2, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->usernameEntry = new GtkEntry();
		$this->usernameEntry->set_width_chars( 16 );
		$tbl->attach( $this->usernameEntry, 1, 2, 1, 2, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Consumer Key: ' );
		$tbl->attach( $lbl, 0, 1, 2, 3, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->consumerKeyEntry = new GtkEntry();
		$this->consumerKeyEntry->set_width_chars( 16 );
		$this->consumerKeyEntry->set_visibility( false );
		$tbl->attach( $this->consumerKeyEntry, 1, 2, 2, 3, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Consumer Secret: ' );
		$tbl->attach( $lbl, 0, 1, 3, 4, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->consumerSecretEntry = new GtkEntry();
		$this->consumerSecretEntry->set_width_chars( 16 );
		$this->consumerSecretEntry->set_visibility( false );
		$tbl->attach( $this->consumerSecretEntry, 1, 2, 3, 4, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Access Token: ' );
		$tbl->attach( $lbl, 0, 1, 4, 5, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->accessTokenEntry = new GtkEntry();
		$this->accessTokenEntry->set_width_chars( 16 );
		$this->accessTokenEntry->set_visibility( false );
		$tbl->attach( $this->accessTokenEntry, 1, 2, 4, 5, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Access Token Secret: ' );
		$tbl->attach( $lbl, 0, 1, 5, 6, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->accessTokenSecretEntry = new GtkEntry();
		$this->accessTokenSecretEntry->set_width_chars( 16 );
		$this->accessTokenSecretEntry->set_visibility( false );
		$tbl->attach( $this->accessTokenSecretEntry, 1, 2, 5, 6, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Use GPS: ' );
		$tbl->attach( $lbl, 0, 1, 6, 7, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->useGpsEntry = new GtkCheckButton();
		$this->useGpsEntry->set_size_request( 80, 80 );
		$this->useGpsEntry->set_alignment( 0.5, 0.5 );
		$tbl->attach( $this->useGpsEntry, 1, 2, 6, 7, Gtk::EXPAND );

		$lbl = new GtkLabel( 'Use WLAN: ' );
		$tbl->attach( $lbl, 0, 1, 7, 8, Gtk::SHRINK );
		$lbl->set_size_request( 180, 80 );
		$this->useWlanEntry = new GtkCheckButton();
		$this->useWlanEntry->set_size_request( 80, 80 );
		$this->useWlanEntry->set_alignment( 0.5, 0.5 );
		$tbl->attach( $this->useWlanEntry, 1, 2, 7, 8, Gtk::EXPAND );

		$tb = new GtkToolbar();
		$tb->set_show_arrow(false);
		$tb->set_property( 'toolbar-style', Gtk::TOOLBAR_BOTH );
		$tb->set_property( 'icon-size', 6 );
		$tb->set_size_request( 480, 80 );

		$btn = GtkToolButton::new_from_stock( Gtk::STOCK_FIND );
		$btn->connect_simple( 'clicked', array( $this, 'showSearches' ) );
		$btn->set_label( 'Searches' );
		$tb->insert( $btn, -1 );

		$btn = GtkToolButton::new_from_stock( Gtk::STOCK_NO );
		$btn->connect_simple( 'clicked', array( $this, 'showFilters' ) );
		$btn->set_label( 'Filters' );
		$tb->insert( $btn, -1 );

		$tbl->attach( $tb, 0, 2, 8, 9 );

		$spacer = new GtkVBox();
		$mainVBox->pack_start( $spacer, true, true );

		$tb = new GtkToolbar();
		$mainVBox->pack_start( $tb, false, false );
		$tb->set_show_arrow(false);
		$tb->set_property( 'toolbar-style', Gtk::TOOLBAR_BOTH );
		$tb->set_property( 'icon-size', 6 );
		$this->closeButton = GtkToolButton::new_from_stock(Gtk::STOCK_OK);
		$this->closeButton->set_label( 'Ok' );
		$this->closeButton->connect_simple( 'clicked', array( $this, 'close' ) );
		$this->closeButton->set_property( 'can-focus', true );
		$tb->insert( $this->closeButton, -1 );

		$this->twitter = $twitter;
		$this->searches = new TwitterSettingsSearchWindow( $twitter );
		$this->filters = new TwitterSettingsFilterWindow( $twitter );
	}

	public function onShow()
	{
		$this->closeButton->grab_focus();
		$settings = $this->twitter->fetchConfigValues();
		$this->usernameEntry->set_text( $settings['username'] );
		$this->consumerSecretEntry->set_text( $settings['consumerSecret'] );
		$this->consumerKeyEntry->set_text( $settings['consumerKey'] );
		$this->accessTokenEntry->set_text( $settings['accessToken'] );
		$this->accessTokenSecretEntry->set_text( $settings['accessTokenSecret'] );
		$this->useGpsEntry->set_active( $settings['useGps'] == 1 );
		$this->useWlanEntry->set_active( $settings['useWlan'] == 1 );
	}

	public function showSearches()
	{
		$this->searches->show_all();
	}

	public function showFilters()
	{
		$this->filters->show_all();
	}

	function close()
	{
		$this->twitter->saveSetting( 'username', $this->usernameEntry->get_text() );
		$this->twitter->saveSetting( 'consumerSecret', $this->consumerSecretEntry->get_text() );
		$this->twitter->saveSetting( 'consumerKey', $this->consumerKeyEntry->get_text() );
		$this->twitter->saveSetting( 'accessToken', $this->accessTokenEntry->get_text() );
		$this->twitter->saveSetting( 'accessTokenSecret', $this->accessTokenSecretEntry->get_text() );
		$this->twitter->saveSetting( 'useGps', $this->useGpsEntry->get_active() ? '1': '0' );
		$this->twitter->saveSetting( 'wlanGps', $this->useWlanEntry->get_active() ? '1': '0' );
		$this->twitter->reloadConfig();
		$this->hide_all();
	}
}
?>
