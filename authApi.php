<?php
require (connexion.php);


function isValidUser ($username, $pwd) {
    require(connexion.php);
    $req = $linkpdo-> prepare("select username, password from utilisateur where username = ?");
    $req->execute(array($username));
    if ($data = $req->fetchAll()) {
        if ($data[0]['password'] == $pwd) {
            return true;
        }
    } else {
        return false;
    }
}
?>