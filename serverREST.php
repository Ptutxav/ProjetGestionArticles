<?php

/// Paramétrage de l'entête HTTP (pour la réponse au Client)
require('connexion.php');
include("jwt_utils.php");
/// Identification du type de méthode HTTP envoyée par le client
$http_method = $_SERVER['REQUEST_METHOD'];
$bearer_token = get_bearer_token();
if (is_jwt_valid($bearer_token)) {
    switch ($http_method) {
        /// Cas de la méthode GET
        case "GET":
            /// Récupération des critères de recherche envoyés par le Client
            $tokenParts = explode('.', $bearer_token);
            $payload = base64_decode($tokenParts[1]);
            $role = json_decode($payload)->role;
            $username = json_decode($payload)->utilisateur;
            switch($role) {
                case "moderator":
                    if (isset($_GET['id']) && !empty($_GET['id'])) {
                        $id = $_GET['id'];
                        $req = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ? ; 
                                                SELECT liker.username as list_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?;
                                                SELECT liker.username as list_dislike FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?;
                                                SELECT count(liker.username) as nb_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?;
                                                SELECT count(liker.username) as nb_dislikes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?;
                                                ");
                        $req->execute(array($id, $id, $id, $id, $id));
                        $matchingData = $req->fetchAll();
                    } else {
                        $req = $linkpdo->prepare("SELECT * FROM article");
                        $req->execute();
                        $matchingData = $req->fetchAll();
                    }
                    break;
                case "publisher":
                    if (isset($_GET['id']) && !empty($_GET['id'])) {
                        $id = $_GET['id'];
                        $req = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ? ;
                                                SELECT count(liker.username) as nb_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?;
                                                SELECT count(liker.username) as nb_dislikes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?;
                                                SELECT * FROM article WHERE article.username = ?;
                                                ");
                        $req->execute(array($id, $id, $id, $username));
                        $matchingData = $req->fetchAll();
                    } else {
                        $req = $linkpdo->prepare("SELECT * FROM article");
                        $req->execute();
                        $matchingData = $req->fetchAll();
                    }
                    break;
                default:
                    $req = $linkpdo->prepare("SELECT * FROM article");
                    $req->execute();
                    $matchingData = $req->fetchAll();
                    break;
            }

            /// Envoi de la réponse au Client
            deliver_response(200, "Votre message", $matchingData);
            break;

        /// Cas de la méthode POST
        case "POST":
            /// Récupération des données envoyées par le Client
            $postedData = file_get_contents('php://input');
            $data = json_decode($postedData);
            $phrase = $data->phrase;
            $req = $linkpdo->prepare("INSERT INTO chuckn_facts (phrase, date_ajout) VALUES (:phrase, NOW())");
            $resExec = $req->execute(array('phrase' => $phrase));

            /// Envoi de la réponse au Client
            deliver_response(201, "Votre message", NULL);
            break;

        /// Cas de la méthode PUT
        case "PUT":
            /// Récupération des données envoyées par le Client
            $postedData = file_get_contents('php://input');
            $data = json_decode($postedData);
            $phrase = $data->phrase;
            $id = $data->id;
            $req = $linkpdo->prepare("UPDATE chuckn_facts set phrase='" . $phrase . "', date_modif=NOW() where id=" . $id);
            $resExec = $req->execute();

            /// Envoi de la réponse au Client
            deliver_response(201, "Votre message", NULL);
            break;

        /// Cas de la méthode DELETE
        case "DELETE":
            //if (!empty($_GET['suppr'])) {
            $req = $linkpdo->prepare("DELETE FROM chuckn_facts WHERE id =" . $_GET['suppr']);
            $req->execute();
            //}
            /// Envoi de la réponse au Client
            deliver_response(200, "Votre message", NULL);
            break;

        /// Cas d'une autre méthode
        default:
            /// Envoi de la réponse au Client
            deliver_response(501, "Méthode non supportée", NULL);
            break;
    }
}
/// Envoi de la réponse au Client
function deliver_response($status, $status_message, $data)
{
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
