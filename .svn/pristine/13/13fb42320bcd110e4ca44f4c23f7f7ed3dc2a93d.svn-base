<?php



namespace App\Controllers;

/**
 * Class Controller
 * @package App\Controllers
 */
class Controller
{
    protected $container;

    /**
     * Controller constructor.
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @param $property
     * @return mixed
     */
    public function __get($property)
    {
        if($this->container->{$property}){
            return $this->container->{$property};
        }
    }
}