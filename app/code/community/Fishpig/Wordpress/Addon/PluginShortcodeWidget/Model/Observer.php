<?php
/**
 * @package Fishpig_Wordpress
 * @url     https://fishpig.co.uk/magento/wordpress-integration/
 */
class Fishpig_Wordpress_Addon_PluginShortcodeWidget_Model_Observer
{
	/*
	 * @var array
	 */
	static protected $inlineScripts = array();
	
	/*
	 * @var array
	 */
	static protected $wpPostCache = array();
	
	/*
	 *
	 */
	protected $router;
	
	/**
	 *
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function applyStringFiltersObserver(Varien_Event_Observer $observer)
	{

		// Fix Documentor counter global
		foreach($GLOBALS as $key => $value) {
			if (strpos($key, 'doc_style_counter_') === 0) {
				unset($GLOBALS[$key]);
			}
		}

		// Get the content, call doShortcode and set the content again
		$contentTransport = $observer->getEvent()->getContent();

		$contentTransport->setContent(
			$this->processString(
				$this->_doShortcode($this->_doShortcode(
					$contentTransport->getContent()
				))
			)
		);
	}

	/*
	 *
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function processString($content)
	{
		// Extract inline scripts
		if (preg_match_all('/<script[^>]{0,}>.*<\/script>/Us', $content, $matches)) {
			foreach($matches[0] as $key => $inlineScript) {
				$this->addInlineScript($inlineScript);
				$content = str_replace($inlineScript, '', $content);
			}
		}
		
		// Extract inline styles
		if (preg_match_all('/<link[^>]{0,}>/Us', $content, $matches)) {
			foreach($matches[0] as $key => $inlineStyle) {
				$this->addInlineScript($inlineStyle);
				$content = str_replace($inlineStyle, '', $content);
			}
		}

		// Gravity Forms
		if (strpos($content, 'gform_wrapper') !== false) {
			if (strpos($content, 'gform_ajax_frame_') !== false) {
				if (preg_match('/<form[^>]{1,}gform[^>]{1,}(action=(["\']{1})[^\#\'"]{1,}(#.*)\\2)/U', $content, $formId)) {
					$content = str_replace($formId[1], 'action="' . Mage::helper('wordpress')->getBaseUrl() . $formId[3] . '"', $content);
				}
			}
		}
		
		// Divi
		if (strpos($content, 'et_pb_') !== false) {
  		if (preg_match_all('/et_pb_([a-z_]+)_([0-9_]+)/', $content, $matches)) {
    		$elements = array('single' => array(), 'double' => array());

    		foreach($matches[1] as $key => $match) {
      		$numbers = $matches[2][$key];

          $type = strpos($numbers, '_') !== false ? 'double' : 'single';

      		if (!isset($elements[$type][$match])) {
        		$elements[$type][$match] = array();
      		}

      		$elements[$type][$match][] = $numbers;
    		}

    		foreach($elements['single'] as $element => $types) {
      		$first = false;
      		
      		foreach($types as $type) {
        		if (!$first) {
          		$first = (int)$type;
        		}
        		else if ($first === 0) {
          		break;
        		}

            $content  = str_replace('et_pb_' . $element . '_' . $type, 'et_pb_' . $element . '_' . ($type - $first), $content);
      		}	
    		}
  		}
		}

		// Revolution Slider
		/*
		if (strpos($content, 'id="rev_slider_') !== false) {
			if (preg_match_all('/id="rev_slider_([0-9]{1,})_([0-9]{1,})"/', $content, $matches)) {
				$sliders = array();
				foreach($matches[0] as $it => $match) {
					
					if (!isset($sliders[$matches[1][$it]])) {
						$sliders[$matches[1][$it]] = array();
					}
					
					$sliders[$matches[1][$it]][count($sliders[$matches[1][$it]])+1] = $matches[2][$it];
				}
			}
			
			foreach($sliders as $sliderId => $its) {
				foreach($its as $key => $it) {
					$content = str_replace('rev_slider_' . $sliderId . '_' . $it, 'rev_slider_' . $sliderId . '_' . $key, $content);
				}
			}
		}
		*/

		return $content;
	}
		
	/*
	 * Get the CSS, JS and other content required for the plugin to function in Magento
	 *
	 * @return bool
	 */
	public function getAssets()
	{
		global $wp_styles, $wp_scripts;

		if (preg_match('/<body[^>]+class="(.*)"/U', $this->getCoreHelper()->getHtml(), $classMatches)) {
			$bodyClasses = explode(' ', str_replace('  ', ' ', trim($classMatches[1])));
			
			$this->addInlineScript('<script type="text/javascript">document.body.className+=\' ' . implode(' ', array_unique($bodyClasses)) . '\';</script>');
		}
		
		$assets = array(
			'head' => array(),
			'inline' => self::$inlineScripts,
			'html'   => array(),
			'footer' => array(),
			'queued' => array(),
		);

		/*
		 * wp_head()
		 */
		if ($wpHead = $this->_getWpHeadOutput()) {
			if (preg_match_all('/<script[^>]{0,}>.*<\/script>/Us', $wpHead, $matches)) {
				foreach($matches[0] as $key => $match) {
					$assets['head']['script_wp_head_' . $key] = $match;
				}
			}
			
			if (preg_match_all('/<style[^>]{0,}>.*<\/style>/Us', $wpHead, $matches)) {
				foreach($matches[0] as $key => $match) {
					$assets['head']['style_wp_head_' . $key] = $match;
				}
			}
		}

		/*
		 * wp_footer()
		 */
		if ($wpFooter = $this->_getWpFooterOutput()) {
			if (preg_match_all('/<script[^>]{0,}>.*<\/script>/Us', $wpFooter, $matches)) {
				foreach($matches[0] as $key => $match) {
					$wpFooter = str_replace($match, '', $wpFooter);
					$assets['footer']['script_wp_footer_' . $key] = $match;
				}
			}
			
			if (preg_match_all('/<style[^>]{0,}>.*<\/style>/Us', $wpFooter, $matches)) {
				foreach($matches[0] as $key => $match) {
					$wpFooter = str_replace($match, '', $wpFooter);
					$assets['footer']['style_wp_footer_' . $key] = $match;
				}
			}
			
			if (trim($wpFooter)) {
				$assets['html']['html_wp_footer'] = $wpFooter;
			}
		}

		/*
		 * Queued Scripts
		 */
		if (isset($wp_scripts) && $wp_scripts->queue) {
			$wp_scripts->do_concat = false;

			foreach($wp_scripts->queue as $item) {
				if (in_array($item, $wp_scripts->done)) {
					continue;
				}

				// Sometimes do_item echo's so this catches that too
				ob_start();
				
				$wp_scripts->do_item($item);
				
				$extra = ob_get_clean();
	
				// We have buffered content
				if ($extra) {
					$assets['queued']['script_' . $item . '_ob'] = $extra;
				}
				
				// We have none buffered (ie. returned) content
				if ($wp_scripts->print_html) {
					$assets['queued']['script_' . $item] = $wp_scripts->print_html;
				}
			}
		}

		/*
		 * Queued Styles
		 */
		if (isset($wp_styles) && $wp_styles->queue) {
			$wp_styles->do_concat = false;
			
			foreach($wp_styles->queue as $item) {
				// Catch any echo'd content
				ob_start();
				
				$wp_styles->do_item($item);
				
				$extra = ob_get_clean();
	
				// We have buffered content
				if ($extra) {
					$assets['queued']['style_' . $item . '_ob'] = $extra;
				}
				
				// We have non-buffered content
				if ($wp_styles->print_html) {
					$assets['queued']['style_' . $item] = $wp_styles->print_html;
				}
			}
		}

		$combined = array();
		
		foreach($assets as $type => $asset) {
			if ($asset) {
				foreach($asset as $a) {
					$combined[] = trim($a);
				}
			}
		}

		return $combined;
	}
	
	
	/*
	 * Get the WordPress post content
	 * This adds support for the Elementor plugin
	 *
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressGetPostContentObserver(Varien_Event_Observer $observer)
	{
		$post      = $observer->getEvent()->getPost();
		$transport = $observer->getEvent()->getTransport();

		try {
			$post->setAsGlobal();

			if ($content = $this->_getElementorProTemplate($post)) {
				
			}
			else {
				$content = $this->getCoreHelper()->simulatedCallback(
					function() {
						global $more;
						
						ob_start();
						
						// Ensure all content is displayed
						$more = 1;
						
						the_content();
						
						return ob_get_clean();
					}
				);
			}

			if (strpos($content, '[product ') !== false) {
				$content = preg_replace_callback('/\[product[^\]]*\]/', function($matches) {
					return str_replace(array('&#8221;', '&#8243;'), '"', $matches[0]);
				}, $content);

				Mage::helper('wordpress/shortcode_product')->apply($content, $post);
			}
			
			if (strpos($content, '{{') !== false) {
				$content = preg_replace('/<p>(\{\{.*\}\})<\/p>/Us', '$1', $content);
				$content = Mage::helper('cms')->getBlockTemplateProcessor()->filter($content);
			}

			$transport->setPostContent($this->processString($content));
		}
		catch (Exception $e) {
			$coreHelper->endWordPressSimulation();
			Mage::helper('wordpress')->log($e);
		}
		
		return $this;
	}
	
	/*
	 * Get the WP_Post object
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressPostSetasglobalBeforeObserver(Varien_Event_Observer $observer)
	{
		$post = $observer->getEvent()->getPost();
		
		if (!isset(self::$wpPostCache[$post->getId()])) {
			self::$wpPostCache[$post->getId()] = $this->getCoreHelper()->simulatedCallback(
				function($post) {
					if ($wpPost = get_post((int)$post->getId())) {
						setup_postdata($wpPost);
						
						return $wpPost;
					}
				
					return false;
				}, 
				array($post)
			);
		}
		
		$post->setWpPostObject(self::$wpPostCache[$post->getId()]);
		
		return $this;
	}
	
	/*
	 *
	 *
	 * @return string
	 */
	protected function _getWpHeadOutput()
	{
		$wpHead = $this->getCoreHelper()->simulatedCallback(function() {
			ob_start();
			wp_head();
		
			return ob_get_clean();
		});

		if (preg_match('/<head>(.*)<\/head>/Us', $this->getCoreHelper()->getHtml(), $match)) {
			$wpHead .= $match[1];
		}
		
		return $wpHead;
	}	

	/*
	 *
	 *
	 * @return string
	 */
	protected function _getWpFooterOutput()
	{
		$wpFooter = $this->getCoreHelper()->simulatedCallback(function() {
			ob_start();
			wp_footer();
		
			return ob_get_clean();
		});

		if (preg_match('/<!--WP-FOOTER-->(.*)<!--\/WP-FOOTER-->/Us', $this->getCoreHelper()->getHtml(), $match)) {
			$wpFooter .= $match[1];
		}
		
		return $wpFooter;
	}
	
	/**
	 * Start WP simulation, run the shortcode and then end WP simulation
	 *
	 * @param string $code
	 * @return mixed
	 */
	protected function _doShortcode($code)
	{
		return $this->getCoreHelper()->doShortcode($code);
	}
	
	/*
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressSidebarWidgetObserver(Varien_Event_Observer $observer)
	{
		$observer->getEvent()
			->getWidget()
				->setBlock('wp_addon_pluginshortcodewidget/sidebar_widget');
		
		return $this;
	}
	
	/*
	 * Add an inline script
	 *
	 * @param string $script
	 * @return $this
	 */
	public function addInlineScript($script)
	{
		self::$inlineScripts[] = $script;
		
		return $this;
	}
	
	/*
	 * Determine whether the current request is a 404 request
	 *
	 * @return bool
	 */
	protected function _is404()
	{
		$html = $this->getCoreHelper()->getHtml();
		
		return strpos($html, '404') !== false && strpos($html, 'not found') !== false;
	}
	
	/*
	 *
	 */
	public static function getCoreHelper()
	{
		return Mage::helper('wp_addon_pluginshortcodewidget/core');
	}	
	
	/*
	 * Determine whether it is the Visual Editor mode
	 *
	 * @return bool
	 */
	public function isVisualEditorMode()
	{
		$keys = array(
			'vc_editable',       // WPBakery Frontend Editor
			'elementor-preview', // Elementor
			'fl_builder',        // BeaverBuilder
			'et_fb',             // Divi
		);
		
		foreach($keys as $key) {
			if (isset($_GET[$key])) {
				return true;
			}
		}
		
		return false;
	}
	
	/*
	 * Add support for Elementor templates
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressRouterSetRoutePathObserver(Varien_Event_Observer $observer)
	{
		$elemLibrary = Mage::app()->getRequest()->getParam('elementor_library');
		$elemPreview = (int)Mage::app()->getRequest()->getParam('elementor-preview');
		
		if (!$elemLibrary && !$elemPreview) {
			return $this;
		}

		$transport = $observer->getEvent()->getTransport();
		
		$transport->setPath(array(
			'module' => 'wordpress',
			'controller' => 'post',
			'action' => 'view',
		));

		$transport->setParams(
			array_merge($transport->getParams(), array('p' => $elemPreview))
		);

		return $this;
	}
	
	/*
	 * Apply automatic Elementor Pro template if exists
	 *
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressPostViewToHtmlObserver(Varien_Event_Observer $observer)
	{
		
		return $this;
	}
	
	/**
	 *
	 *
	 *
	 */
	protected function _getElementorProTemplate($post)
	{
		if (!$this->getCoreHelper()->simulatedCallback(function() {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
			return is_plugin_active('elementor-pro/elementor-pro.php');
		})) {
			return false;
		}

		$isTemplateApplied = $this->getCoreHelper()->simulatedCallback(function($postId, $postType) {
			$conds = get_option('elementor_pro_theme_builder_conditions');

			if (is_array($conds['single'])) {
				foreach($conds['single'] as $templateId => $types) {
					if (in_array('include/singular/' . $postType, $types)) {
						return true;
					}

					if (in_array('include/singular/' . $postType . '/' . $postId, $types)) {
						return true;
					}
				}
			}
			
			return false;
		}, array($post->getId(), $post->getPostType()));
		
		if (!$isTemplateApplied) {
			return false;
		}

		$html = $this->getCoreHelper()->getHtml();

		if (preg_match('/<body[^>]*>(.*)<\/body>/Us', $html, $matches)) {
			return preg_replace('/<script[^>]*>.*<\/script>/Us', '', $matches[1]);
		}

		return false;
	}
	
	/**
	 *
	 *
	 */
	public function wordpressMatchRoutesBeforeAgainObserver(Varien_Event_Observer $observer)
	{
		$this->router = $observer->getEvent()
			->getRouter()
				->addRouteCallback(array($this, 'getPswRoutes'));
	}
	
	/**
	 *
	 *
	 */
	public function getPswRoutes()
	{
		/* Handle dynamic routes */
		$isErrorPage = $this->getCoreHelper()->simulatedCallback(function() {			
			return is_404();
		});
		
		if (!$isErrorPage) {
			$this->router->addRoute('/.*/', 'wp_addon_pluginshortcodewidget/index/dynamic', []);
		}

		return $this;
	}
}
