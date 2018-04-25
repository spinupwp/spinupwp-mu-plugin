<?php

class SpinupwpAutoloader
{
    /**
     * @var string
     */
    protected $path;

    /**
     * SpinupwpAutoloader constructor.
     */
    public function __construct($path)
    {
        $this->path = $path;

        spl_autoload_register([$this, 'autoloader']);
    }

    /**
     * Autoload the class files.
     *
     * @param string $class_name
     */
    public function autoloader($class_name)
    {
        if (!$this->classBelongsToPlugin($class_name)) {
            return;
        }

        $path = $this->getClassesDirectory() . $this->getClassPath($class_name);

        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Class belong to plugin.
     *
     * @param string $class_name
     *
     * @return bool
     */
    protected function classBelongsToPlugin($class_name)
    {
        if (0 !== strpos($class_name, 'DeliciousBrains\Spinupwp')) {
            return false;
        }

        return true;
    }

    /**
     * Get classes directory.
     *
     * @return string
     */
    protected function getClassesDirectory()
    {
        return $this->path . DIRECTORY_SEPARATOR . 'spinupwp' . DIRECTORY_SEPARATOR;
    }

    /**
     * Get a classes path.
     *
     * @param string $class_name
     * @return string
     */
    protected function getClassPath($class_name)
    {
        $parts    = explode('\\', $class_name);
        $parts    = array_slice($parts, 2);
        $filename = implode(DIRECTORY_SEPARATOR, $parts) . '.php';

        return $filename;
    }
}
