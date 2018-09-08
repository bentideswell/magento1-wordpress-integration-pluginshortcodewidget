<?php
/*
 *
 */
class Fishpig_Wordpress_Addon_PluginShortcodeWidget_Block_Sidebar_Widget extends Mage_Core_Block_Text
{
	/*
	 *
	 */
	protected function _toHtml()
	{
		try {
			$widgetId    = $this->getWidgetType() . '-' . $this->getWidgetIndex();
			$widgetOptions = $this->_getInstanceOptions($this->getWidgetType(), $this->getWidgetIndex());

			$widgetHtml    = Mage::helper('wp_addon_pluginshortcodewidget/core')->simulatedCallback(
				function($widgetId, $widgetOptions) {
			    $instance = false;
		
			    global $wp_widget_factory, $wp_registered_widgets;

					$args = array(
						'widget_id' => $widgetId,
						'widget_name' => isset($wp_registered_widgets[$widgetId]['name']) ? $wp_registered_widgets[ $widgetId ]['name'] : '',
						'before_widget' => '<div class="block block-blog">',
						'after_widget' => '</div>',
						'before_title' => '<div class="block-title"><strong><span>',
						'after_title' => '</span></strong></div>'
			    );
		
			    if (!empty($widgetOptions['title'])) {
				    $args['after_title'] .= '<div class="block-content">';
				    $args['after_widget'] = '</div></div>';
			    }
			    else {
				    $args['before_widget'] .= '<div class="block-content">';
				    $args['after_widget'] = '</div></div>';
			    }

					foreach($wp_widget_factory->widgets as $key => $value) {
						if ($value->id === $widgetId) {
							$instance = $value;
							$widgetId = $key;
							break;
						}
					}

					if (!$instance) {
						return false;
					}

					$newInstance = clone $instance;

					if ($widgetOptions) {
						foreach($widgetOptions as $option => $value) {
							$newInstance->$option = $value;
						}
					}
	
			    ob_start();
	    
			    the_widget($widgetId, $newInstance, $args);
			    
			    $output = ob_get_clean();
			    $output = str_replace('<li>', '<li class="item">', $output);
					
					return $output;
				},
				array($widgetId, $widgetOptions)
			);
			
			if ($widgetHtml) {
				if (preg_match_all('/(<script[^>]{0,}>)(.*)(<\/script>)/Us', $widgetHtml, $matches)) {
					foreach($matches[0] as $key => $script) {
						$widgetHtml = str_replace($script, '', $widgetHtml);
	
						Mage::getSingleton('wp_addon_pluginshortcodewidget/observer')->addInlineScript($script);
					}	
				}
	
				return $widgetHtml;
		  }
	  }
	  catch (Exception $e) {
		  Mage::helper('wordpress')->log($e->getMessage());
	  }
	  
	  return '';
	}
	
	/*
	 * Get the instance options
	 *
	 * @param string $type
	 * @param int $id
	 * @return array
	 */
	protected function _getInstanceOptions($type, $id)
	{
		if ($options = Mage::helper('wordpress')->getWpOption('widget_' . $type)) {
			if ($options = $this->_unserialize($options)) {
				if (isset($options[$id])) {
					return $options[$id];
				}
			}
		}

		return array();
	}
	
	/*
	 * PHP version safe unserialize function
	 *
	 * @param string $s
	 * @return array|false
	 */
	protected function _unserialize($s)
	{
		if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
			return @unserialize($s, array('allowed_classes' => false));
		}
		
		return @unserialize($s);
	}
}
