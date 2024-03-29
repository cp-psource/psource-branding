<?php
/**
 * PSToolkit Author Box Widget class.
 *
 * @since 2.0.0
 *
 * @package PSToolkit
 * @subpackage Front-end
 */
class Author_Box_Widget extends WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'   => __CLASS__,
			'description' => __( 'Autorenbox', 'ub' ),
		);
		parent::__construct( __CLASS__, __( 'Autorenbox', 'ub' ), $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo apply_filters( 'author_box', false );
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		echo wpautop( __( 'Widget hat keine Konfiguration.', 'ub' ) );
	}
}
