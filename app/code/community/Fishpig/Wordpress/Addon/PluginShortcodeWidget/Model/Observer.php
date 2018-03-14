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
	
	/**
	 *
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function applyStringFiltersObserver(Varien_Event_Observer $observer)
	{
		$contentTransport = $observer->getEvent()->getContent();
		$content = $contentTransport->getContent();

		$content = $this->_doShortcode($this->_doShortcode($content));
		
		// Extract inline scripts
		if (preg_match_all('/<script[^>]{0,}>.*<\/script>/Us', $content, $matches)) {
			foreach($matches[0] as $key => $inlineScript) {
				$this->addInlineScript($inlineScript);
				
				$content = str_replace($inlineScript, '', $content);
			}
		}

		/*
		 * Gravity Forms
		 */		 
		if (strpos($content, 'gform_wrapper') !== false) {
			if (strpos($content, 'gform_ajax_frame_') !== false) {
				if (preg_match('/<form[^>]{1,}gform[^>]{1,}(action=(["\']{1})[^\#\'"]{1,}(#.*)\\2)/U', $content, $formId)) {
					$content = str_replace($formId[1], 'action="' . Mage::helper('wordpress')->getBaseUrl() . $formId[3] . '"', $content);
				}
			}
		}
		
		$contentTransport->setContent($content);
	}
	
	/*
	 * Get the CSS, JS and other content required for the plugin to function in Magento
	 *
	 * @return bool
	 */
	public function getAssets()
	{
#		echo Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml();exit;
	
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
	 *
	 *
	 * @return string
	 */
	protected function _getWpHeadOutput()
	{
		return preg_match(
			'/<!--WP-HEADER-->(.*)<!--\/WP-HEADER-->/Us', Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml(), $match)
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
}
