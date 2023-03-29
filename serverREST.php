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
                            getPublisherID($id, 'Get ok !');
                        } else {
                           //prepare
                            $article = $linkpdo->prepare("SELECT * FROM article");
                            $nb_likes = $linkpdo->prepare("SELECT count(liker.username) as nb_likes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = 1");
                            $nb_dislikes = $linkpdo->prepare("SELECT count(liker.username) as nb_dislikes FROM liker, article WHERE liker.id_article = article.id_article AND like_status = -1");
                            //execute
                            $article->execute();
                            //fetch
                            if ($matchingData = $article->fetchAll(PDO::FETCH_ASSOC)) {
                                foreach()
                                $matchingData = array_merge($matchingData, $nb_likes->fetchAll(PDO::FETCH_ASSOC));
                                $matchingData = array_merge($matchingData, $nb_dislikes->fetchAll(PDO::FETCH_ASSOC));
                                deliver_response(200, $mes, $matchingData);
                            } else {
                                deliver_response(404, "Not found", null);                                
                            }
                        }
                        break;
                }
                break;

            case "POST":
                if ($role == "publisher") {
                    // Récupération des données envoyées par le Client
                    $postedData = file_get_contents('php://input');
                    $data = json_decode($postedData, true);
                    if (isset($data['contenu'])) {
                        $req = $linkpdo->prepare("INSERT into article(date_publication, contenu, username) values (?,?,?)");
                        $req->execute(array(date('Y-m-d H:i:s'), $data['contenu'], $username));
                        if ($req->rowCount() < 1) {
                            deliver_response(500, "Erreur serveur", null);
                            break;
                        }
                        $pubId = $linkpdo->lastInsertId();
                        getPublisher($pubId, 'article ajouté avec succès');
                    }
                }
                break;

            case "PUT":
                if ($role == "publisher" && isset($_GET['id'])) {
                    $getArticleWithId = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                    $getArticleWithId->execute(array($_GET['id']));
                    //si l'article existe
                    if ($getArticleWithId->rowCount() >= 1) {
                        //liker
                        if (isset($_GET['like']) && !isset($_GET['dislike'])) {
                            $selectLike = $linkpdo->prepare("SELECT * FROM liker WHERE id_article = ? AND username = ?");
                            $selectLike->execute(array($_GET['id'], $username));
                            if($selectLike->rowCount() >= 1) {
                                echo "hello";
                                $updateLike = $linkpdo->prepare("UPDATE liker SET like_status = 1 WHERE id_article = ? AND username = ?");
                                $updateLike->execute(array($_GET['id'], $username));
                            } else {
                                $insertLike = $linkpdo->prepare("INSERT INTO liker (id_article, username, like_status) values (?, ?, 1)");
                                var_dump($username);
                                $insertLike->execute(array($_GET['id'], $username));
                            }
                            deliver_response(200, "OK", null);
                        //disliker
                        } elseif (!isset($_GET['like']) && isset($_GET['dislike'])) {
                            $selectLike = $linkpdo->prepare("SELECT * FROM liker WHERE id_article = ? AND username = ?");
                            $selectLike->execute(array($_GET['id'], $username));
                            if($selectLike->rowCount() >= 1) {
                                $updateDislike = $linkpdo->prepare("UPDATE liker SET like_status = -1 WHERE id_article = ? AND username = ?");
                                $updateDislike->execute(array($_GET['id'], $username));
                            } else {
                                $insertDislike = $linkpdo->prepare("INSERT INTO liker (id_article, username, like_status) values (?, ?, -1)");
                                $insertDislike->execute(array($_GET['id'], $username));
                            }
                            deliver_response(200, "OK", null);

                        } else {
                            $dataUsername = $getArticleWithId->fetch();
                            $usernameToCompare = $dataUsername['username'];
                            if ($usernameToCompare == $username) {
                                $postedData = file_get_contents('php://input');
                                $data = json_decode($postedData);
                                $contenu = $data->contenu;
                                if ($contenu != null) {
                                    $updateArticle = $linkpdo->prepare("UPDATE article SET contenu = ? WHERE id_article = ?");
                                    $updateArticle->execute(array($contenu, $_GET['id']));
                                    deliver_response(201, "Update successful", null);
                                } else {
                                    deliver_response(400, "Bad request : contenu null", null);
                                }
                            } else {
                                deliver_response(401, "Unauthorized", null);
                            }
                        }
                    } else {
                        deliver_response(404, "Not found", null);
                    }
                } else {
                    deliver_response(401, "Unauthorized", null);
                }
                break;

            case "DELETE":
                switch ($role) {
                    case "moderator":
                        if (isset($_GET['id'])) {
                            $delete = $linkpdo->prepare("DELETE FROM article WHERE article.id_article = ?");
                            $delete->execute(array($_GET['id']));

                            if ($delete->rowCount() < 1) {
                                deliver_response(404, "Not found", NULL);
                            } else {
                                deliver_response(200, "OK : suppression effectuée avec succès", NULL);
                            }
                        } else {
                            deliver_response(400, "Bad request : id incorrect", NULL);
                        }
                        break;
                    case "publisher":
                        if (isset($_GET['id'])) {
                            $delete = $linkpdo->prepare("DELETE FROM article WHERE article.id_article = ? AND article.username = ?");
                            $delete->execute(array($_GET['id'], $username));
                            if ($delete->rowCount() < 1) {
                                deliver_response(404, "Not found or insufficient permissions", NULL);
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
        deliver_response(401, "Unauthorized : expired or invalid token", NULL);
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
            deliver_response(401, "Unauthorized : insufficient permissions", NULL);
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

function getPublisherID($id, $mes)
{
    require('connexion.php');
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
        deliver_response(200, $mes, $matchingData);
    } else {
        deliver_response(404, "Not found", null);
    }
}
