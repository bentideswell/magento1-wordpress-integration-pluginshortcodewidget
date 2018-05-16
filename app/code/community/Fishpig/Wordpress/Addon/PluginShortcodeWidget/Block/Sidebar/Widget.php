<?php
/*
 *
 * @Obfuscate
 */
class Fishpig_Wordpress_Addon_PluginShortcodeWidget_Block_Sidebar_Widget extends Mage_Core_Block_Text
{
	/*
	 *
	 */
	protected function _toHtml()
	{
		try {
			$widgetName = $this->getWidgetType() . '-' . $this->getWidgetIndex();
	    $instance = false;

	    global $wp_widget_factory;
	    
			foreach($wp_widget_factory->widgets as $key => $value) {
				if ($value->id === $widgetName) {
					$instance = $value;
					$widgetName = $key;
					break;
				}
			}
			
			if (!$instance) {
				return '';
			}    
	
			$newInstance = clone $instance;
	
			$widgetOptions = $this->_getInstanceOptions($this->getWidgetType(), $this->getWidgetIndex());
			
			if ($widgetOptions) {
				foreach($widgetOptions as $option => $value) {
					$newInstance->$option = $value;
				}
			}

	    ob_start();
	
			$args = array(
				'widget_id'=>$widgetName,
				'before_widget' => '<div class="block block-blog">',
				'after_widget' => '</div>',
				'before_title' => '<div class="block-title"><strong><span>',
				'after_title' => '</span></strong></div>'
	    );
	    
	    if ($title) {
		    $args['after_title'] .= '<div class="block-content">';
		    $args['after_widget'] = '</div></div>';
	    }
	    else {
		    $args['before_widget'] .= '<div class="block-content">';
		    $args['after_widget'] = '</div></div>';
	    }
	    
	    the_widget($widgetName, $newInstance, $args);
	    
	    $output = ob_get_clean();
	    $output = str_replace('<li>', '<li class="item">', $output);

			if (preg_match_all('/(<script[^>]{0,}>)(.*)(<\/script>)/Us', $output, $matches)) {
				foreach($matches[0] as $key => $script) {
					$output = str_replace($script, '', $output);

					Mage::getSingleton('wp_addon_pluginshortcodewidget/observer')->addInlineScript($script);
				}	
			}

	    return $output;
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
			if ($options = @unserialize($options, array('allowed_classes' => false))) {
				if (isset($options[$id])) {
					return $options[$id];
				}
			}
		}
		
		return array();
	}
	
}