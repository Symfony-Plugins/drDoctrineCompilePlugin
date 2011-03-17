<?php

/**
 * Task for compiling the Doctrine classes in to a single file
 * 
 */
class drDoctrineCompileTask extends sfBaseTask
{
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('doctrine_path', null, sfCommandOption::PARAMETER_OPTIONAL, 'The path where Doctrine.php may be found', null), 
      new sfCommandOption('compiled_path', 'path', sfCommandOption::PARAMETER_OPTIONAL, 'The path where you want to write the compiled doctrine libs.', null), 
      new sfCommandOption('compiler_path', null, sfCommandOption::PARAMETER_OPTIONAL, 'The path where you want to write the compiler to.', null),
      new sfCommandOption('drivers', null, sfCommandOption::PARAMETER_OPTIONAL, 'Specify list of drivers you wish to compile. Ex: mysql,mssql,sqlite', null),
      new sfCommandOption('no-drivers', null, sfCommandOption::PARAMETER_NONE, 'Use this option to include no drivers in the compile process'), 
    ));
    
    $this->namespace = 'doctrine';
    $this->name = 'compile-core';
    $this->briefDescription = 'Compile the Doctrine core';
    
    $this->detailedDescription = <<<EOF
The [doctrine:compile-core|INFO] task compiles many used Doctrine classes into one file.

Call it with:

  [php symfony doctrine:compile-core|INFO]
  
By default, the task also compiles classes for the database drivers that are currently in use, according to your databases.yml file.

If you wish to specify the drivers by hand, use:

	[php symfony doctrine:compile-core --drivers=mysql,mssql|INFO]
  
If you don't want to include any drivers, use:

	[php symfony doctrine:compile-core --no-drivers|INFO]
EOF;
  }
  
  /**
   * Execute the task
   * 
   * @see sfTask::execute()
   */
  protected function execute($arguments = array(), $options = array())
  {
    $options = $this->processOptions($options);
    
    if (count($options['drivers']))
    {
      $this->logSection('task', sprintf('Compile Doctrine core classes and classes for these drivers: [%s]', implode(', ', $options['drivers'])));
    }
    else
    {
      $this->logSection('task', sprintf('Compile Doctrine core classes'));
    }
    
    try
    {
      $this->logSection('doctrine', 'Start compiling...');
      
      $compiler = new drDoctrineCompiler($options['doctrine_path'], $options['compiled_path'], $options['compiler_path'], $options['drivers']);
      
      $compiler->setFilesystem($this->getFilesystem());
      
      $target = $compiler->compile();
      
      $this->logSection('done', sprintf('Compiled classes were saved to "%s"', $target));
    }
    catch (Doctrine_Compiler_Exception $e)
    {
      throw new sfCommandException(sprintf('Doctrine compile error: %s', $e->getMessage()));
    }
  }
  
  /**
   * Process the options array, set defaults
   * 
   * @param array $options
   * 
   * @return array
   */
  protected function processOptions(array $options = array())
  {
    if ($options['no-drivers'])
    {
      $options['drivers'] = array();
    }
    else
    {
      if (null === $options['drivers'])
      {
        $options['drivers'] = $this->getDefaultDoctrineDrivers();
      }
      
      if (!is_array($options['drivers']))
      {
        $options['drivers'] = explode(',', $options['drivers']);
      }
    }
    
    if (null === $options['compiled_path'])
    {
      $options['compiled_path'] = $this->getDefaultDoctrineCompiledPath();
    }
    
    if (null === $options['compiler_path'])
    {
      $options['compiler_path'] = $this->getDefaultDoctrineCompilerPath();
    }
    
    if (null === $options['doctrine_path'])
    {
      $options['doctrine_path'] = $this->getDoctrinePath();
    }
    
    return $options;
  }
  
  /**
   * Get the path to the Doctrine core class file
   * 
   */
  protected function getDoctrinePath()
  {
    return drDoctrineCompilePluginConfiguration::getDoctrinePath();
  }
  
	/**
   * Get the path of the file that contains the compiled Doctrine classes
   * 
   * @return string
   */
  protected function getDefaultDoctrineCompiledPath()
  {
    return drDoctrineCompilePluginConfiguration::getDoctrineCompiledPath();
  }
  
  /**
   * Get the path of the generated compiler file
   * 
   * @return string
   */
  protected function getDefaultDoctrineCompilerPath()
  {
    return sfConfig::get('app_doctrine_compiler_path', dirname($this->getDefaultDoctrineCompiledPath()) . '/Doctrine.compiler.php');
  }
  
  
  /**
   * Get an array of Doctrine drivers that are used by the current project
   * 
   * @return DoctrineCompileTask::getDoctrineDrivers()
   */
  protected function getDefaultDoctrineDrivers()
  {
    return sfConfig::get('app_doctrine_drivers', $this->collectDoctrineDriversFromConfigFile());
  }
  
  /**
   * Collect an array of driver names, based on the current database configuration found in databases.yml
   * 
   * @return array
   */
  protected function collectDoctrineDriversFromConfigFile()
  {
    $databases_yml = ProjectConfiguration::getActive()->getRootDir() . '/config/databases.yml';
    if (!file_exists($databases_yml))
    {
      throw new LogicException(sprintf('File "%s" not found', $databases_yml));
    }
    
    $drivers = array();
    $config = sfYaml::load($databases_yml);
    
    foreach ($config as $name => $database)
    {
      foreach ($database as $database_config)
      {
        if (isset($database_config['param']) && isset($database_config['param']['dsn']))
        {
          $dsn = $database_config['param']['dsn'];
          
          // the name of the used Doctrine driver is the first part of the dsn, before the colon
          $dsn = explode(':', $dsn);
          
          if (isset($dsn[0]) && $dsn[0])
          {
            $driver = $dsn[0];
            if (!in_array($driver, $drivers))
            {
              $drivers[] = $driver;
            }
          }
        }
      }
    }

    return $drivers;
  }
}
