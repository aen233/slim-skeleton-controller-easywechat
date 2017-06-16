<?php


require_once 'vendor/autoload.php';

ORM::configure(array(
    'connection_string' => 'mysql:host=localhost;dbname=_wx1_cnsaga',
    'username' => 'root',
    'password' => ''
));

ORM::configure('return_result_sets', true); // returns result sets
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));


// documented and default style
$person = ORM::for_table('ims_mc_members')->where('mobile', '15877323434')->find_one();


$list = $person->get('mobile');

var_dump($list);

// PSR-1 compliant style
$person = ORM::forTable('ims_mc_members')->where('mobile', '15877323434')->findOne();



/***************************************

$people = ORM::for_table('person')
    ->where_any_is(array(
        array('score' => '5', 'age' => 10),
        array('score' => '15', 'age' => 20)), '>')
    ->find_many();

// Creates SQL:
$sql = "SELECT * FROM `widget` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))";
//var_dump($person);

******/

$number_of_people = ORM::for_table('ims_mc_members')->count();


var_dump($number_of_people);
/*
$person = ORM::for_table('ims_mc_members')->where('mobile','15877323434')->find_one()->find_result_set();

foreach($person as $record){
    var_dump($record);
}

*/


//var_dump($person);
