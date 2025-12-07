## Asset Mapper

## Troubleshooting

La définition des chemins dans le fichier `asset_mapper.yaml` doit respecter le format de liste indexée pour la clé `paths`.
Utiliser un tableau associatif génère une erreur indiquant que le chemin vers app n'est pas trouvé.

De fait, il n'est pas possible de charger des librairies avec npm, puis de les ajouter dans le fichier `asset_mapper.yaml` :
`npm install @mdi/font --save-dev` ne fonctionne pas, même si les paquets sont bien chargés.

La solution consiste à les charger avec la commande `php bin/console assetmap:require @mdi/font`.

Cependant, il arrive que le paquet ne soit pas disponible sur `jsDelivr` (erreur 404).
Il faut alors se replier sur l'import via CDN, en l'incluant dans le bloc `stylesheets` dans le template twig :

```html
{% block stylesheets %}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
{% endblock %}
```
#### Chargement de la font Roboto Variable

>NOTE : Variable est un format moderne permettant de changer la taille de la police sans modifier le contenu.

Il faut charger le lien depuis `jsDelivr`, copier le lien dans le bloc `stylesheets` du template twig :

```html
{% block stylesheets %}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource-variable/roboto@5.2.9/index.min.css">
{% endblock %}
```

Ensuite, ajouter dans la balise body du fichier app.css :

```css
body {
    font-family: 'Roboto Variable', sans-serif;
}
```

### Problème XDEBUG

Après avoir configuré correctement le debug à la fois localement et à distance,
j'ai perdu les connexions lors d'une autre session.

Pour régler le problème, il a fallu que je supprime pui que je recrée le serveur dans PHPSTORM :
le mappage ne se faisait plus correctement.

Je l'ai déclaré comme ceci :

Name : 192.168.1.23
Host : webtrader.local
Port : 80
Cocher Use path mapping
Faire correspondre C:/laragon/www/webtrader_assetmapper -> /var/www/html

>NOTE : webtrader.local est le nom de domaine de mon serveur local. POur être reconnu,
> il doit être déclaré dans le fichier hosts de la machine (`C:\Windows\System32\drivers\etc\hosts`)
> en ajoutant l'entrée : 192.168.1.23 webtrader.local

#### Ajout du debug local

Pour retrouver la connexion de debug, j'ai dû déclarer le serveur local dans PHPSTORM :

Name : Local
Host : 127.0.0.1
Port : 8000
Ne pas cocher Use path mapping
