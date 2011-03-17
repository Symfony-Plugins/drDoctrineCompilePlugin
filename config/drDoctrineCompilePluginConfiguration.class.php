<?php 

/**
 * Configuration class for drDoctrineCompilePlugin
 * 
 */
class drDoctrineCompilePluginConfiguration extends sfPluginConfiguration
{
  static protected $doctrine_loaded = false;
  
  static public 
    $doctrine_compiled_path,
    $doctrine_path = null;
  
  public function setup()
  {
    if (sfConfig::get('app_doctrine_auto_compile', true))
    {
      $this->dispatcher->connect('command.post_command', array($this, 'listenToCommandPostCommand'));
    }
  }
    
  /**
   * Initialize the plugin: load the compiled Doctrine classes if they exist, otherwise, load Doctrine from the vendor directory
   * 
   * @see sfPluginConfiguration::initialize()
   */
  public function initialize()
  {
    if (!self::$doctrine_loaded)
    {
      if (class_exists('Doctrine'))
      {
        throw new sfPluginException('The drDoctrineCompilePlugin should be initialized before the sfDoctrinePlugin (see config/ProjectConfiguration.class.php)');
      }
      
      $doctrine_compiled_path = self::getDoctrineCompiledPath();      
      if (file_exists($doctrine_compiled_path))
      {
        // load Doctrine from the compiled file 
        require_once $doctrine_compiled_path;
      }
      else
      {
        // load Doctrine from the vendor directory
        require_once sfConfig::get('sf_doctrine_dir', self::getDoctrinePath()) . '/Doctrine.php';
      }
      
      Doctrine::setPath(self::getDoctrinePath());
      
      // trick the sfDoctrinePlugin into loading a fake Doctrine
      sfConfig::set('sf_doctrine_dir', dirname(__FILE__) . '/../lib/doctrine');
      
      self::$doctrine_loaded = true;
    }
  }
  
  /**
   * Listens to the command.post_command event
   * 
   * @param sfEvent $event
   */
  static public function listenToCommandPostCommand(sfEvent $event)
  {
    $task = $event->getSubject();
    /* @var $task sfBaseTask */
    
    if ($task->getNamespace() == 'cache' && $task->getName() == 'clear')
    {
      $doctrine_compile_task = new drDoctrineCompileTask(new sfEventDispatcher(), $task->getFormatter());
      $doctrine_compile_task->run(array(), array());
    }
  }
  
  /**
   * Get the path of the file that contains the compiled Doctrine classes
   * 
   * @return string
   */
  static public function getDoctrineCompiledPath()
  {
    if (null === self::$doctrine_compiled_path)
    {
      self::$doctrine_compiled_path = sfConfig::get('app_doctrine_compiled_path', sfConfig::get('sf_cache_dir').'/doctrine/Doctrine.compiled.php');
    }
    
    return self::$doctrine_compiled_path;
  }
  
  /**
   * Get the path of the Doctrine vendor directory
   * 
   * @return string
   */
  static public function getDoctrinePath()
  {
    if (null === self::$doctrine_path)
    {
      self::$doctrine_path = sfConfig::get('sf_symfony_lib_dir').'/plugins/sfDoctrinePlugin/lib/vendor/doctrine';
    }
    
    return self::$doctrine_path;
  }
}
