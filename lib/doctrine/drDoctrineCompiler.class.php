<?php 

/**
 * Wrapper class for the Doctrine compiler
 *
 */
class drDoctrineCompiler
{
  protected 
    $_filesystem,
    $_doctrine_path,
    $_compiler_path,
    $_compiled_path,
    $_drivers;
  
  /**
   * 
   * @param string $doctrine_path The directory that contains Doctrine.php
   * @param string $compiled_path The path the compiled classes should be save to (including the filename)
   * @param string $compiler_path The path the generated compiler should be saved to (including the filename)
   * @param array $drivers An array containing the names of database drivers for which the Doctrine classes should be compiled too
   */
  public function __construct($doctrine_path, $compiled_path, $compiler_path, array $drivers = array())
  {
    $this->_doctrine_path = $doctrine_path;
    $this->_compiled_path = $compiled_path;
    $this->_drivers = $drivers;
    $this->_compiler_path = $compiler_path;
  }
  
  /**
   * Inject the filesystem (e.g. from a task)
   * 
   * @param sfFilesystem $filesystem
   */
  public function setFilesystem(sfFilesystem $filesystem)
  {
    $this->_filesystem = $filesystem;
  }
  
  /**
   * @return sfFilesystem
   */
  public function getFilesystem()
  {
    if (null === $this->_filesystem)
    {
      $this->_filesystem = new sfFilesystem();
    }
    
    return $this->_filesystem;
  }

  /**
   * Generates a temporary compiler script that compiles the Doctrine code base
   * 
   * @throws drDoctrineCompilerException
   */
  protected function generateCompiler()
  {
    // TODO this doesn't work on a Windows server, provide an alternative for it
    
    $this->getFilesystem()->mkdirs(dirname($this->_compiler_path));
    
    $php = "#!/usr/bin/env php
<?php
require_once('%path_to_doctrine%');
spl_autoload_register(array('Doctrine', 'autoload'));

try 
{ 
  \$target = Doctrine::compile('%compiled_path%', array(%drivers%));
  echo \$target;
  exit(1);
} 
catch (Doctrine_Compiler_Exception \$e)
{
  echo \$e->getMessage();
  exit(0);
}
";
    
    $drivers = array();
    foreach ($this->_drivers as $driver)
    {
      $drivers[] = sprintf("'%s'", $driver);
    }
    
    $php = strtr($php, array(
    	'%compiled_path%' => $this->_compiled_path, 
    	'%drivers%' => implode(', ', $drivers),
      '%path_to_doctrine%' => $this->_doctrine_path . '/Doctrine.php',
    ));
    
    if (!file_put_contents($this->_compiler_path, $php))
    {
      throw new drDoctrineCompilerException(sprintf('Could not save the generated compiler file "%s"', $this->_compiler_path));
    }

    $this->getFilesystem()->chmod($this->_compiler_path, 0775);
    
    if (!is_executable($this->_compiler_path))
    {
      throw new drDoctrineCompilerException(sprintf('Could not make the generated compiler file "%s" executable by the user', $this->_compiler_path));
    }
  }
  
  /**
   * Compile the Doctrine classes into a single file
   * 
   * @return string The name of the compilation file
   */
  public function compile()
  {
    $this->generateCompiler();
    
    $this->getFilesystem()->mkdirs(dirname($this->_compiled_path));
    
    return $this->doCompile();
  }
  
  /**
   * Do the real compilation
   * 
   * @throws drDoctrineCompilerException
   * 
   * @return string The name of the compilation file
   */
  protected function doCompile()
  {
    $output = array();
    $return_value = null;
    
    exec($this->_compiler_path, $output, $return_value);
    
    if ($return_value === 0)
    {
      throw new drDoctrineCompilerException(array_shift($output));
    }
    else if ($return_value === 1)
    {
      $target = array_shift($output);
      
      $this->getFilesystem()->remove($this->_compiler_path);
      
      if (file_exists($this->_compiler_path))
      {
        throw new drDoctrineCompilerException(sprintf('Could not delete the generated compiler file "%s"', $this->_compiler_path));
      }
      
      return $target;
    }
    else
    {
      throw new drDoctrineCompilerException(sprintf('The compiler returned an unknown value: "%s"', $return_value));
    }
  } 
}
