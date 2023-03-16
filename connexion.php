<?php

	$host = 'localhost';
	$bddname = 'projetapirest';
	$user = 'root';
	$pwd = 'root';

    //Connexion au serveur MySQL
    try {
        $linkpdo = new PDO("mysql:host=$host;dbname=$bddname;charset=utf8mb4", $user, $pwd, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"));
    }
    ///Capture des erreurs éventuelles
    catch (Exception $e) {
        die('Erreur : ' . $e->getMessage());
    }
    
?>