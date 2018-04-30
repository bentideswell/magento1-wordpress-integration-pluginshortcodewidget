<?php
/**
 * @category Fishpig
 * @package Fishpig_Wordpress
 * @license http://fishpig.co.uk/license.txt
 * @author Ben Tideswell <help@fishpig.co.uk>
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
		
		// Revolution Slider
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

		$assets = array(
			'head' => array(),
			'inline' => self::$inlineScripts,
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
					$assets['footer']['script_wp_footer_' . $key] = $match;
				}
			}
			
			if (preg_match_all('/<style[^>]{0,}>.*<\/style>/Us', $wpFooter, $matches)) {
				foreach($matches[0] as $key => $match) {
					$assets['footer']['style_wp_footer_' . $key] = $match;
				}
			}
		}

		/*
		 * Queued Scripts
		 */
		if ($wp_scripts->queue) {
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
		if ($wp_styles->queue) {
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
			if ($post->getMetaValue('_elementor_edit_mode') !== 'builder') {
				return $this;
			}
			
			$coreHelper = Mage::helper('wp_addon_pluginshortcodewidget/core');
			
			if (!$coreHelper->isActive()) {
				return $this;
			}
			
			$coreHelper->startWordPressSimulation();
			$post->setAsGlobal();
			
			ob_start();
			
			the_content();
			
			$content = ob_get_clean();

			$coreHelper->endWordPressSimulation();

			$transport->setPostContent($this->processString($content));
		}
		catch (Exception $e) {
			Mage::helper('wordpress')->log($e);
			$coreHelper->endWordPressSimulation();
		}
		
		return $this;
	}
	
	/*
	 *
	 *
	 * @return string
	 */
	protected function _getWpHeadOutput()
	{
		if ($this->_is404()) {
			ob_start();
			wp_head();
			
			return ob_get_clean();
		}

		return preg_match('/<head>(.*)<\/head>/Us', Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml(), $match)
			? $match[1]
			: '';
	}	

	/*
	 *
	 *
	 * @return string
	 */
	protected function _getWpFooterOutput()
	{
		if ($this->_is404()) {
			ob_start();
			wp_footer();
			
			return ob_get_clean();
		}

		return preg_match('/<!--WP-FOOTER-->(.*)<!--\/WP-FOOTER-->/Us', Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml(), $match)
			? $match[1]
			: '';
	}
	
	/**
	 * Start WP simulation, run the shortcode and then end WP simulation
	 *
	 * @param string $code
	 * @return mixed
	 */
	protected function _doShortcode($code)
	{
		try {
			$coreHelper = Mage::helper('wp_addon_pluginshortcodewidget/core');
			
			if ($coreHelper->isActive()) {
				$coreHelper->startWordPressSimulation();
				$value = do_shortcode($code);
				$coreHelper->endWordPressSimulation();
				
				// Fix HTML entity data parameters
				$value = str_replace(array('&#091;', '&#093;'), array('[', ']'), $value);
	
				return $value;
			}
		}
		catch (Exception $e) {
			Mage::helper('wordpress')->log($e);
		}
		
		return $code;
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
	 * Get the WP_Post object
	 *
	 * @param  Varien_Event_Observer $observer
	 * @return $this
	 */
	public function wordpressPostSetasglobalBeforeObserver(Varien_Event_Observer $observer)
	{
		$post = $observer->getEvent()->getPost();
		
		if (!isset(self::$wpPostCache[$post->getId()])) {
			self::$wpPostCache[$post->getId()] = Mage::helper('wp_addon_pluginshortcodewidget/core')->simulatedCallback(
				function($post) {
					if ($wpPost = get_post((int)$post->getId())) {
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
	 * Determine whether the current request is a 404 request
	 *
	 * @return bool
	 */
	protected function _is404()
	{
		$html = Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml();
		
		return strpos($html, '404') !== false && strpos($html, 'not found') !== false;
	}
}
