# ProjetGestionArticles
 Conception et développement d’API REST pour la gestion d’articles

Authentification :

POST
http://localhost/ProjetGestionArticles/authApi.php

Comptes :

Modérateur
{
    "username":"mod",
    "password":"1234"
}

Publisher 1
{
    "username":"pub1",
    "password":"1234"
}

Publisher 2
{
    "username":"pub2",
    "password":"1234"
}

Publisher 3
{
    "username":"pub3",
    "password":"1234"
}


Serveur : 

GET
http://localhost/ProjetGestionArticles/serverRest.php
With id :
http://localhost/ProjetGestionArticles/serverRest.php?id=

POST
http://localhost/ProjetGestionArticles/serverRest.php

PUT
http://localhost/ProjetGestionArticles/serverRest.php

PATCH
like :
http://localhost/ProjetGestionArticles/serverRest.php?id=&like
dislike :
http://localhost/ProjetGestionArticles/serverRest.php?id=&dislike

DELETE
http://localhost/ProjetGestionArticles/serverRest.php?id=
