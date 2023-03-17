<?php
require ('connexion.php');
require ('jwt_utils.php');

/// Paramétrage de l'entête HTTP (pour la réponse au Client)
header("Content-Type:application/json");

/// Identification du type de méthode HTTP envoyée par le client
$http_method = $_SERVER['REQUEST_METHOD'];
switch ($http_method){
    case "POST" :
        $data = (array) json_decode(file_get_contents('php://input'), true);

        if (isValidUser($data['username'], $data['password'])) {
            $username = $data['username'];

            $headers = array('alg'=>'HS256', 'typ'=>'JWT');
            $playload = array('username'=>$username, 'exp'=>(time() + 60));

            $jwt = generate_jwt($headers, $playload);

            deliver_response(200, "connexion établie", NULL);
        } else {
            deliver_response(400, "username ou mot de passe invalide", NULL);
        }
    break;

    default :
        deliver_response(400, "requete http reçue non traitée par le serveur", NULL);
}

function isValidUser ($username, $password) {
    require('connexion.php');
    $req = $linkpdo->prepare("SELECT password from utilisateur where username = ?");
    $req->execute(array($username));
    if ($data = $req->fetch()) {
        if ($data[0] == $password) {
            return true;
        }
    } else {
        return false;
    }
}

function deliver_response($status, $status_message, $data){
    /// Paramétrage de l'entête HTTP, suite
   header("HTTP/1.1 $status $status_message");
   /// Paramétrage de la réponse retournée
   $response['status'] = $status;
   $response['status_message'] = $status_message;
   $response['data'] = $data;
   /// Mapping de la réponse au format JSON
   $json_response = json_encode($response);
   echo $json_response;
   }
?>