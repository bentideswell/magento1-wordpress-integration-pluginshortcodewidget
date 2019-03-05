<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Addon_PluginShortcodeWidget_IndexController extends Mage_Core_Controller_Front_Action
{
	/**
	  * Display the category page and list blog posts
	  *
	  */
	public function dynamicAction()
	{
		try {
			$this->loadLayout();
				
			$layout = $this->getLayout();
			
			$layout->getBlock('root')->setTemplate('page/1column.phtml');
			
			if (!preg_match('/<body[^>]+>(.*)<\/body>/Us', Mage::helper('wp_addon_pluginshortcodewidget/core')->getHtml(), $match)) {
				throw new Exception('No HTML found for current route in WordPress.');
			}
			
			$html = preg_replace('/<script[^>]*>.*<\/script>/sU', '', $match[1]);

			$textBlock = $layout->createBlock('core/text')->setText($html);

			$layout->getBlock('content')->append($textBlock);

			$this->renderLayout();
		}
		catch (Exception $e) {
			exit($e);
			Mage::helper('wordpress')->log($e);
			
			return $this->_forward('noRoute');
		}
	}
}
