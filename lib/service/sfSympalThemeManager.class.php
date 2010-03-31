<?php
/**
 * Class that manages the current theme
 * 
 * There should be one theme per context instance
 * 
 * @package     sfSympalThemePlugin
 * @subpackage  theme
 * @author      Ryan Weaver <ryan@thatsquality.com>
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @since       2010-03-27
 * @version     svn:$Id$ $Author$
 */
class sfSympalThemeManager
{
  /**
   * Dependencies
   */
  protected
    $_controller,
    $_request,
    $_response;
  
  /**
   * @var boolean Whether or not the current theme has been loaded
   */
  protected $_isLoaded = false;
  
  /**
   * @var string $_currentTheme  The current theme name
   * @var array  $_themeObjects  Array of the instantiated sfSympalTheme objects
   */  
  protected
    $_currentThemeName,
    $_themeObjects = array();
  
  public function __construct(sfController $controller, sfRequest $request, sfResponse $response)
  {
    $this->_controller = $controller;
    $this->_request = $request;
    $this->_response = $response;
  }

  /**
   * Sets the given theme as the current theme and loads it up
   * 
   * This does everything from setting the template to adding stylesheets
   * and javascripts
   * 
   * @param string $theme The name of the theme to load
   */
  public function setCurrentTheme($theme)
  {
    if ($theme != $this->getCurrentThemeName())
    {
      // unload the current theme
      $this->_unloadCurrentTheme();
      
      // set the current theme and load it
      $this->_currentThemeName = $theme;
      $this->_loadCurrentTheme();
    }
  }

  /**
   * Loads the current theme if not already loaded
   */
  protected function _loadCurrentTheme()
  {
    if ($this->isLoaded() || !$theme = $this->getCurrentTheme())
    {
      return;
    }

    // Change the layout
    $this->_changeLayout($theme->getLayoutPath());

    // Add theme stylesheets to response
    $this->_addStylesheets($theme->getStylesheets());

    // Add theme javascripts to response
    $this->_addJavascripts($theme->getJavascripts());

    // Invoke any callables
    $this->_invokeCallables($theme->getCallables());

    // Set loaded flag
    $this->_isLoaded = true;
  }
  
  /**
   * Unloads the current theme
   */
  protected function _unloadCurrentTheme()
  {
    if (!$theme = $this->getCurrentTheme())
    {
      return;
    }

    // Remove theme stylesheets
    $this->_removeStylesheets($theme->getStylesheets());

    // Remove theme javascripts
    $this->removeJavascripts($theme->getJavascripts());
    
    $this->_isLoaded = false;
  }

  /**
   * Changes the current layout to the given layout path
   */
  protected function _changeLayout($layoutPath)
  {
    $info = pathinfo($layoutPath);
    $path = $info['dirname'].'/'.$info['filename'];
    
    $actionEntry = $this->_controller->getActionStack()->getLastEntry();
    $module = $actionEntry ? $actionEntry->getModuleName() : $this->_request->getParameter('module');
    $action = $actionEntry ? $actionEntry->getActionName() : $this->_request->getParameter('action');

    sfConfig::set('symfony.view.'.$module.'_'.$action.'_layout', $path);
    sfConfig::set('symfony.view.sympal_default_error404_layout', $path);
    sfConfig::set('symfony.view.sympal_default_secure_layout', $path);
  }

  /**
   * Adds the given stylesheets to the response object
   * 
   * @param array $stylesheets The stylesheets to add to the response
   */
  protected function _addStylesheets($stylesheets)
  {
    foreach ($stylesheets as $stylesheet)
    {
      $this->_response->addStylesheet(sfSympalConfig::getAssetPath($stylesheet), 'last');
    }
  }

  /**
   * Adds the given javascripts to the response object
   * 
   * @param array $javascripts The javascripts to add to the response
   */
  protected function _addJavascripts($javascripts)
  {
    foreach ($javascripts as $javascript)
    {
      $this->_response->addJavascript(sfSympalConfig::getAssetPath($javascript));
    }
  }

  /**
   * Calls the given array of callables
   * 
   * @param array $callables The array of callables to call
   */
  protected function _invokeCallables($callables)
  {
    foreach ($callables as $callable)
    {
      if (count($callable) > 1)
      {
        call_user_func($callable);
      }
      else
      {
        call_user_func($callable[0]);
      }
    }
  }

  /**
   * Removes the array of stylesheets from the response
   */
  protected function _removeStylesheets($stylesheets)
  {
    foreach ($stylesheets as $stylesheet)
    {
      $this->_response->removeStylesheet(sfSympalConfig::getAssetPath($stylesheet));
    }
  }

  /**
   * Removes the array of javascripts from the response
   */
  public function _removeJavascripts($javascripts)
  {
    foreach ($javascripts as $javascript)
    {
      $this->_response->removeJavascript(sfSympalConfig::getAssetPath($javascript));
    }
  }

  /**
   * Returns whether or not the current theme has been loaded
   * 
   * @return boolean
   */
  public function isLoaded()
  {
    return $this->_isLoaded;
  }

  /**
   * Returns the name of the currently loaded theme
   * 
   * @return string
   */
  public function getCurrentThemeName()
  {
    return $this->_currentThemeName;
  }

  /**
   * Returns the current theme object, if there is one
   * 
   * @return sfSympalTheme or false if there is not current theme
   */
  public function getCurrentTheme()
  {
    return $this->getCurrentThemeName() ? $this->getTheme($this->getCurrentThemeName()) : false;
  }

  /**
   * Get the theme object for a given theme name
   *
   * @param string $name 
   * @return sfSympalTheme $theme
   */
  public function getTheme($theme)
  {
    if (!isset($this->_themeObjects[$theme]))
    {
      $config = sfSympalConfig::get('themes', $theme);
      if (!$config)
      {
        throw new sfException(sprintf('Cannot load the "%s" theme: no configuration found.'));
      }

      $themeClass = isset($config['theme_class']) ? $config['theme_class'] : 'sfSympalTheme';
      unset($config['theme_class']);

      $this->_themeObjects[$theme] = new $themeClass($theme, $config);
    }

    return $this->_themeObjects[$theme];
  }
}