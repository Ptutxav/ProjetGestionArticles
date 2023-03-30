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
                            $article = $linkpdo->prepare("SELECT article.*, 
                            (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = 1 AND liker.id_article = ?) as nb_likes, 
                            (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = -1 AND liker.id_article = ?) as nb_dislikes,
                            GROUP_CONCAT(DISTINCT CASE WHEN liker.like_status = -1 AND liker.id_article = ? THEN liker.username ELSE NULL END) as users_disliked,
                            GROUP_CONCAT(DISTINCT CASE WHEN liker.like_status = 1 AND liker.id_article = ? THEN liker.username ELSE NULL END) as users_liked
                            FROM article
                            LEFT JOIN liker ON liker.id_article = article.id_article
                            WHERE article.id_article = ?
                            GROUP BY article.id_article;
                            ");
                            //execute
                            $article->execute(array($id, $id, $id, $id, $id));
                            //fetch
                            $matchingData = $article->fetch(PDO::FETCH_ASSOC);
                            if($article->rowCount() == 1) {
                                deliver_response(200, "GET OK", $matchingData);
                            } else {
                                deliver_response(404, "Not found", null);
                            }
                        } else {
                            $articles = $linkpdo->prepare("SELECT article.*, 
                            (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = 1) as nblikes, 
                            (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = -1) as nbdislikes,
                            GROUP_CONCAT(DISTINCT CASE WHEN liker.like_status = -1 THEN liker.username ELSE NULL END) as users_disliked,
                            GROUP_CONCAT(DISTINCT CASE WHEN liker.like_status = 1 THEN liker.username ELSE NULL END) as users_liked
                            FROM article
                            LEFT JOIN liker ON liker.id_article = article.id_article
                            GROUP BY article.id_article;
                            ");
                            $articles->execute();
                            $matchingData = $articles->fetchAll(PDO::FETCH_ASSOC);
                            deliver_response(200, "get ok",$matchingData);
                        }
                        break;

                    case "publisher":
                        if (isset($_GET['id'])) {
                            $id = $_GET['id'];
                            getPublisherID($id,200, 'Get ok !');
                        } else {
                           //prepare
                            $article = $linkpdo->prepare("SELECT article.*, (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = 1) as nblikes, 
                            (SELECT COUNT(id_article) FROM liker WHERE liker.id_article = article.id_article AND liker.like_status = -1) as nbdislikes FROM article");
                            //execute
                            $article->execute();
                            //fetch
                            if ($matchingData = $article->fetchAll(PDO::FETCH_ASSOC)) {
                                deliver_response(200, "get ok", $matchingData);
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
                        getPublisher($pubId, 201,'article ajouté avec succès');
                    }
                }
                break;

            case "PUT":
                if ($role == "publisher" && isset($_GET['id'])) {
                    $id = $_GET['id'];
                    $getArticleWithId = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                    $getArticleWithId->execute(array($id));
                    //si l'article existe
                    if ($getArticleWithId->rowCount() == 1) {
                        $dataUsername = $getArticleWithId->fetch();
                        $usernameToCompare = $dataUsername['username'];
                        if ($usernameToCompare == $username) {
                            $postedData = file_get_contents('php://input');
                            $data = json_decode($postedData);
                            $contenu = $data->contenu;
                            if ($contenu != null) {
                                $updateArticle = $linkpdo->prepare("UPDATE article SET contenu = ? WHERE id_article = ?");
                                $updateArticle->execute(array($contenu, $_GET['id']));
                                getPublisherID($id, 200,"update success");
                            } else {
                                deliver_response(400, "Bad request : contenu null", null);
                            }
                        }
                    } else {
                        deliver_response(404, "Not found", null);
                    }
                } else {
                    deliver_response(401, "Unauthorized : seul un utilisateur publisher peut modifier son article", null);
                }
                break;

            case "PATCH":
                if ($role == "publisher" && isset($_GET['id'])) {
                    $id= $_GET['id'];
                    $getArticleWithId = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                    $getArticleWithId->execute(array($id));
                    //si l'article existe
                    if ($getArticleWithId->rowCount() == 1) {
                        //liker
                        if (isset($_GET['like']) && !isset($_GET['dislike'])) {
                            $selectLike = $linkpdo->prepare("SELECT * FROM liker WHERE id_article = ? AND username = ?");
                            $selectLike->execute(array($id, $username));
                            if ($selectLike->rowCount() == 1) {
                                $updateLike = $linkpdo->prepare("UPDATE liker SET like_status = 1 WHERE id_article = ? AND username = ?");
                                $updateLike->execute(array($id, $username));
                            } else {
                                $insertLike = $linkpdo->prepare("INSERT INTO liker (id_article, username, like_status) values (?, ?, 1)");
                                $insertLike->execute(array($id, $username));
                            }
                            getPublisherID($id, 200, "Ok");
                            //disliker
                        } elseif (!isset($_GET['like']) && isset($_GET['dislike'])) {
                            $selectLike = $linkpdo->prepare("SELECT * FROM liker WHERE id_article = ? AND username = ?");
                            $selectLike->execute(array($_GET['id'], $username));
                            if ($selectLike->rowCount() == 1) {
                                $updateDislike = $linkpdo->prepare("UPDATE liker SET like_status = -1 WHERE id_article = ? AND username = ?");
                                $updateDislike->execute(array($id, $username));
                            } else {
                                $insertDislike = $linkpdo->prepare("INSERT INTO liker (id_article, username, like_status) values (?, ?, -1)");
                                $insertDislike->execute(array($id, $username));
                            }
                            getPublisherID($id, 200, "Ok");

                        } else {
                            deliver_response(400, "Bad request", null);
                        }
                    } else {
                        deliver_response(404, "Not found", null);
                    }
                } else {
                    deliver_response(401, "Unauthorized : Seul un utlisateur publisher peux liker un contenu", null);
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
                            deliver_response(400, "Bad request : Saisir l'identifiant", NULL);
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
                            deliver_response(400, "Bad request : Saisir l'identifiant", NULL);
                        }
                        break;
                }
                break;

            // Cas d'une autre méthode
            default:
                // Envoi de la réponse au Client
                deliver_response(501, "Not Implemented : Méthode non supportée", NULL);
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
            if(isset($_GET['id'])) {
                //prepare
                $article = $linkpdo->prepare("SELECT * FROM article WHERE id_article = ?");
                //execute
                $article->execute(array($_GET['id']));
                if($article->rowCount() != 1) {
                    deliver_response(404, "Not found", null);
                    break;
                }
                //fetch
                $matchingData = $article->fetchAll(PDO::FETCH_ASSOC);
                deliver_response(200, "GET OK", $matchingData);
            } else {
                //prepare
                $articles = $linkpdo->prepare("SELECT * FROM article");
                //execute
                $articles->execute();
                //fetch
                $matchingData = $articles->fetchAll(PDO::FETCH_ASSOC);
                deliver_response(200, "GET OK", $matchingData);
            }
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

function getPublisherID($id, $code ,$mes)
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
        deliver_response($code, $mes, $matchingData);
    } else {
        deliver_response(404, "Not found", null);
    }
}
