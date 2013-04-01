<?php
class TwitterSettingsFilterWindow extends GtkWindow
{
	public $twitter = null;

	function __construct( $twitter )
	{
		parent::__construct();

		$this->twitter = $twitter;
		$this->set_size_request( 480, 600 );
		$this->set_title( 'Filters' );
		$this->connect_simple( 'show', array( $this, 'onShow' ) );

		$mainVBox = new GtkVBox();
		$this->add( $mainVBox );

		$sw = new GtkScrolledWindow();
		$sw->set_property( 'hscrollbar-policy', Gtk::POLICY_NEVER );
		$mainVBox->pack_start( $sw, true, true );

		$this->filtersVbox = new GtkVBox();
		$sw->add_with_viewport( $this->filtersVbox );

		$addHBox = new GtkHBox();
		$mainVBox->pack_start( $addHBox, false, false );

		$label = new GtkLabel( 'Add: ' );
		$addHBox->pack_start($label, false, false);

		$this->entry = new GtkEntry();
		$addHBox->pack_start( $this->entry, true, true );

		$this->addButton = GtkToolButton::new_from_stock( Gtk::STOCK_ADD );
		$this->addButton->set_label( 'Add' );
		$this->addButton->connect_simple( 'clicked', array( $this, 'addFilter') );
		$this->addButton->set_property( 'width-request', 50 );
		$this->addButton->set_property( 'height-request', 50 );
		$this->addButton->set_property( 'can-focus', true );
		$addHBox->pack_start( $this->addButton, false, false );

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
	}

	function onShow()
	{
		$this->closeButton->grab_focus();
		$this->reloadFilters();
	}

	function close()
	{
		$this->hide_all();
	}

	function delete( $a, $n )
	{
		$this->twitter->delFilter( $n );
		$this->reloadFilters();
	}

	function addFilter()
	{
		$text = $this->entry->get_text();
		$text = trim( $text );
		if ( strlen( $text ) > 0 )
		{
			$this->twitter->addFilter( $text );
			$this->entry->set_text( '' );
			$this->reloadFilters();
			$this->addButton->grab_focus();
		}
	}

	function reloadFilters()
	{
		if ( !$this->twitter )
		{
			return;
		}
		$searches = $this->twitter->fetchFilters();
		$children = $this->filtersVbox->get_children();
		foreach ( $children as $child )
		{
			$this->filtersVbox->remove( $child );
		}

		foreach ( $searches as $search )
		{
			$box = new GtkHbox();

			$this->delButton = GtkToolButton::new_from_stock( Gtk::STOCK_DELETE );
			$this->delButton->set_label( 'Add' );
			$this->delButton->connect( 'clicked', array( $this, 'delete' ), $search );
			$this->delButton->set_property( 'width-request', 50 );
			$this->delButton->set_property( 'height-request', 50 );
			$this->delButton->show();
			$box->pack_start( $this->delButton, false, false );

			$lbl = new GtkLabel( $search );
			$box->pack_start( $lbl, true, true );

			$this->filtersVbox->pack_start( $box, false, false );
		}
		$this->show_all();
	}
}
