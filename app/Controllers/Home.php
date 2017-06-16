<?php

namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


/**
 * Class Home
 * @package App\Controllers
 */
class Home extends Controller
{
    public function home($request, $response)
    {
        return $this->view->render($response, 'home.twig');
    }

    public function users($request, $response)
    {
        $username = 'elliot';
        $data = [
            ['username' => 'elliot','password' => 'datas'],
            ['username' => 'elliots','password' => 'datals'],
        ];
        return $this->view->render($response,'home.twig',
            ['username' => $username,'data'=>$data]);
    }



    public function ccheck()
    {
        $members=$this->db->members()->limit(10);
        foreach ($members as $data) {
            echo $data['nickname']."<br/>";
        }
    }

    public function admin($request, $response)
    {
        return $this->renderer->render($response, 'adminer.php');
    }
}