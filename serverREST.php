<?php
require('connexion.php');
require("jwt_utils.php");

// Identification du type de méthode HTTP envoyée par le client
$http_method = $_SERVER['REQUEST_METHOD'];
$bearer_token = get_bearer_token();

//utilisateur authentifié
if (get_authorization_header() != null) {
    if (is_jwt_valid($bearer_token)) {
        //Découpage du payload
        $tokenParts = explode('.', $bearer_token);
        $payload = base64_decode($tokenParts[1]);
        $role = json_decode($payload)->role;
        $username = json_decode($payload)->utilisateur;
        switch ($http_method) {
            case "GET":
                switch ($role) {
                    case "moderator":
                        if (isset($_GET['id'])) {
                            $id = $_GET['id'];
                            //prepare
                            $article = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                            $list_likes = $linkpdo->prepare("SELECT liker.username as list_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?;");
                            $list_dislikes = $linkpdo->prepare("SELECT liker.username as list_dislike FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?;");
                            $nb_likes = $linkpdo->prepare("SELECT count(liker.username) as nb_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?;");
                            $nb_dislikes = $linkpdo->prepare("SELECT count(liker.username) as nb_dislikes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?;");
                            //execute
                            $article->execute(array($id));
                            $list_likes->execute(array($id));
                            $list_dislikes->execute(array($id));
                            $nb_likes->execute(array($id));
                            $nb_dislikes->execute(array($id));
                            //fetch
                            if ($matchingData = $article->fetch(PDO::FETCH_ASSOC)){
                                $matchingData = array_merge($matchingData, $list_likes->fetchAll(PDO::FETCH_ASSOC));
                                $matchingData = array_merge($matchingData, $list_dislikes->fetchAll(PDO::FETCH_ASSOC));
                                $matchingData = array_merge($matchingData, $nb_likes->fetch(PDO::FETCH_ASSOC));
                                $matchingData = array_merge($matchingData, $nb_dislikes->fetch(PDO::FETCH_ASSOC));
                                deliver_response(200, "GET OK", $matchingData);
                            } else {
                                deliver_response(404, "Not found", null);                                
                            }
                        } else {
                            $articles = $linkpdo->prepare("SELECT * FROM article");
                            $articles->execute();
                            $matchingData = $articles->fetchAll(PDO::FETCH_ASSOC);
                        }
                        break;

                    case "publisher":
                        if (isset($_GET['id'])) {
                            $id = $_GET['id'];
                            //prepare
                            $article = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                            $nb_likes = $linkpdo->prepare("SELECT count(liker.username) as nb_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1 AND liker.id_article = ?");
                            $nb_dislikes = $linkpdo->prepare("SELECT count(liker.username) as nb_dislikes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1 AND liker.id_article = ?");
                            //execute
                            $article->execute(array($id));
                            $nb_likes->execute(array($id));
                            $nb_dislikes->execute(array($id));
                            //fetch
                            if ($matchingData = $article->fetch(PDO::FETCH_ASSOC)) {
                                $matchingData = array_merge($matchingData, $nb_likes->fetch(PDO::FETCH_ASSOC));
                                $matchingData = array_merge($matchingData, $nb_dislikes->fetch(PDO::FETCH_ASSOC));
                                deliver_response(200, "GET OK", $matchingData);
                            } else {
                                deliver_response(404, "Not found", null);                                
                            }
                        } else {
                            //prepare
                            $articles = $linkpdo->prepare("SELECT * FROM article");
                            $own_articles = $linkpdo->prepare("SELECT * FROM article WHERE article.username = ?");
                            //execute
                            $articles->execute();
                            $own_articles->execute(array($username));
                            //fetch
                            $matchingData = $articles->fetchAll(PDO::FETCH_ASSOC);
                            $matchingData = array_merge($matchingData, $own_articles->fetchAll(PDO::FETCH_ASSOC));
                        }
                        break;
                }
                break;

            case "POST":
                // Récupération des données envoyées par le Client
                $postedData = file_get_contents('php://input');
                $data = json_decode($postedData);
                
                break;

            case "PUT":
                // Récupération des données envoyées par le Client
                $postedData = file_get_contents('php://input');
                $data = json_decode($postedData);
                $phrase = $data->phrase;
                $id = $data->id;
                $req = $linkpdo->prepare("UPDATE chuckn_facts set phrase='" . $phrase . "', date_modif=NOW() where id=" . $id);
                $resExec = $req->execute();

                // Envoi de la réponse au Client
                deliver_response(201, "Votre message", NULL);
                break;

            case "DELETE":
                //Découpage du payload
                $tokenParts = explode('.', $bearer_token);
                $payload = base64_decode($tokenParts[1]);
                $role = json_decode($payload)->role;
                $username = json_decode($payload)->utilisateur;

                switch ($role) {
                    case "moderator":
                        if (isset($_GET['id'])) {
                            $delete = $linkpdo->prepare("DELETE FROM article WHERE article.id_article = ?");
                            $delete->execute(array($_GET['id']));

                            if($delete->rowCount() < 1) {
                                deliver_response(404, "Not found", NULL);
                            } else {
                                deliver_response(200, "OK : suppression effectuée avec succès", NULL);
                            }
                        } else {
                            deliver_response(400, "Bad request : id incorrect", NULL);
                        }
                        break;
                }
                break;

            // Cas d'une autre méthode
            default:
                // Envoi de la réponse au Client
                deliver_response(501, "Méthode non supportée", NULL);
                break;
        }
    //token invalide ou expiré
    } else {
        deliver_response(401, "Accès Refusé : Token invalide ou expiré", NULL);
    }
//utilisateur non authentifié
} else {
    switch ($http_method) {
        case "GET":
            //prepare
            $articles = $linkpdo->prepare("SELECT * FROM article");
            //execute
            $articles->execute();
            //fetch
            $matchingData = $articles->fetchAll(PDO::FETCH_ASSOC);
            deliver_response(200, "GET OK", $matchingData);
            break;

        default:
            // Envoi de la réponse au Client
            deliver_response(501, "Méthode non supportée", NULL);
            break;
    }
}

// Envoi de la réponse au Client
function deliver_response($status, $status_message, $data)
{
    // Paramétrage de l'entête HTTP
    header("HTTP/1.1 $status $status_message");
    // Paramétrage de la réponse retournée
    $response['status'] = $status;
    $response['status_message'] = $status_message;
    $response['data'] = $data;
    // Mapping de la réponse au format JSON
    $json_response = json_encode($response);
    echo $json_response;
}
