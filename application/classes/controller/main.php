<?php

defined('SYSPATH') or die('No direct script access.');

class Controller_Main extends Controller_Template
{
		public $template = 'template';
		protected $auth_required = false;
		protected $secure_actions = false;
		protected $css_files = array();
		protected $login_user_id = null;
		protected $redirect_url = '/';
		protected $blocks = array('content' => null, 'header' => null, 'footer' => null, 'menu' => null);

		public function before()
		{
				parent::before();

				I18n::lang('pl');

				$action_name = Request::instance()->action;

				if (($this->auth_required !== false && Auth::instance()->logged_in($this->auth_required) === false)
						|| (is_array($this->secure_actions) && array_key_exists($action_name, $this->secure_actions)
						&& Auth::instance()->logged_in($this->secure_actions[$action_name]) === false))
				{
					if (Auth::instance()->logged_in())
					{
						Request::instance()->redirect('auth/noaccess');
					}
					else
					{
						Request::instance()->redirect('auth/signin');
					}
				}

				$this->login_user_id = is_object(Auth::instance()->get_user()) ? Auth::instance()->get_user()->get_pk() : null;

				if ($this->auto_render)
				{
						foreach (Kohana::config('application') as $setting_name => $setting_value)
						{
								if ( ! isset($this->$setting_name))
								{
										$this->{$setting_name} = $setting_value;
								}

								if (in_array($setting_name, self::$bind_global))
								{
										View::bind_global($setting_name, $this->{$setting_name});
								}
								else
								{
										$this->template->bind($setting_name, $this->{$setting_name});
								}
						}
				}
		}

		public function after()
		{
				if ($this->auto_render)
				{
						foreach ($this->blocks as $block_name => $block_content)
						{
								if (empty($block_content) && method_exists($this, 'block_' . $block_name))
								{
										$this->blocks[$block_name] = $this->{'block_' . $block_name}();
								}
						}
						
						View::set_global('blocks', $this->blocks);
						View::set_global('login_user', Auth::instance()->get_user());
				}
				parent::after();
		}

		protected function block_header()
		{
				return View::factory('header');
		}

		protected function block_footer()
		{
				return View::factory('footer');
		}

		protected function block_menu()
		{
				return View::factory('menu');
		}

		public static $bind_global = array('application_title', 'application_motto', 'copyright', 'action_title');

}

?>
