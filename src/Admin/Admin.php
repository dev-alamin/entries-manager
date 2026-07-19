<?php

namespace Amin\FormsEntriesManager\Admin;

defined( 'ABSPATH' ) || exit;

use Amin\FormsEntriesManager\Assets;
use Amin\FormsEntriesManager\Admin\Options;
use Amin\FormsEntriesManager\Admin\Menu;
use Amin\FormsEntriesManager\Admin\Admin_Notice;
use Amin\FormsEntriesManager\GoogleSheet\Admin_UI;
use Amin\FormsEntriesManager\GoogleSheet\Send_Data;
use Amin\FormsEntriesManager\Admin\Logs\HandleLogAction;

/**
 * Class Admin
 * * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Admin {


	/**
	 * Assets instance.
	 *
	 * @var Assets
	 */
	protected $assets;

	/**
	 * Options instance.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Menu instance.
	 *
	 * @var Menu
	 */
	protected $menu;

	/**
	 * Admin_Notice instance.
	 *
	 * @var Admin_Notice
	 */
	protected $admin_notice;

	/**
	 * Admin_UI instance
	 *
	 * @var Admin_UI
	 */
	protected $admin_ui;

	/**
	 * Send_Data instance
	 *
	 * @var mixed
	 */
	protected $send_data;

	/**
	 * HandleLogAction instance
	 *
	 * @var HandleLogAction
	 */
	protected $handle_log_action;

	/**
	 * Initializes and manages all admin-related functionalities by instantiating and connecting various services.
	 *
	 * @param Assets       $assets       The assets handler.
	 * @param Options      $options      The options handler.
	 * @param Menu         $menu         The menu handler.
	 * @param Admin_Notice $admin_notice The admin notice handler.
	 * @param Send_Data    $send_data    The data sender for Google Sheets.
	 */
	public function __construct(
		Assets $assets,
		Options $options,
		Menu $menu,
		Admin_Notice $admin_notice,
		Send_Data $send_data,
		HandleLogAction $handle_log_action
	) {
		$this->assets            = $assets;
		$this->options           = $options;
		$this->menu              = $menu;
		$this->admin_notice      = $admin_notice;
		$this->send_data         = $send_data;
		$this->handle_log_action = $handle_log_action;
	}
}
