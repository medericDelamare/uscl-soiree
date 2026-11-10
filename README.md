# Soirée US Cormeilles Lieurey

Réservation et paiement en ligne (HelloAsso) de la soirée dansante du club : les licenciés réservent une ou plusieurs places sans compte, paient, reçoivent un **billet à QR code** par e-mail. À l'entrée, les bénévoles scannent le QR code avec l'appareil photo de leur téléphone.

Stack : Symfony 7.4 · PHP 8.4 · MySQL 8.4 · EasyAdmin 5 · PhpSpreadsheet · endroid/qr-code · AssetMapper (pas de Node). Même socle que la boutique, projet et base séparés.

## Démarrage

```bash
cp .docker/.env.dist .docker/.env
echo "APP_SECRET=$(openssl rand -hex 16)" > .env.local
make init
.docker/bin/console app:user:create bureau@uscormeilleslieurey.fr "Bureau" --admin
.docker/bin/console app:user:create entree@uscormeilleslieurey.fr "Entrée"
```

| URL | |
|---|---|
| http://localhost:8190 | Page de réservation |
| http://localhost:8190/admin | Back-office (comptes `--admin`) |
| http://localhost:8190/controle | Contrôle à l'entrée (tous les comptes) |
| http://localhost:8192 | Maildev (e-mails envoyés) |

## Réglages de la soirée

Tout est dans `config/services.yaml` (paramètres `app.event.*`) : date, lieu, prix en centimes, âge limite du tarif enfant, jauge (230), durée de retenue des places (30 min), date limite d'inscription, menu affiché. À adapter chaque année.

## Fonctionnement

- Une **réservation** = un payeur (nom, prénom, e-mail, téléphone) + N **convives** (nom, prénom, âge, plat). Le tarif dépend de l'âge (moins de 13 ans : enfant), figé à la réservation.
- Les places sont retenues 30 minutes pendant le paiement. `app:booking:purge-pending` (cron toutes les 15 min) annule les réservations expirées, après avoir revérifié auprès de HelloAsso.
- La jauge est contrôlée sous verrou MySQL (`GET_LOCK`) : deux réservations simultanées ne peuvent pas dépasser 230 places.
- **Billet** : un QR code par réservation, qui contient l'adresse `/controle/<jeton>`. Le bénévole connecté voit les convives et valide l'entrée de chacun (ou de tous), avec protection contre la réutilisation. Sans billet : recherche par nom.
- **Back-office** : totaux par plat et catégorie pour le traiteur, liste des réservations et des convives, export Excel, « Marquer payée » pour un règlement en espèces ou par chèque (envoie le billet), annulation.
- `app:booking:purge-old` supprime les données 6 mois après la soirée (annoncé dans la page confidentialité).

## Paiement

- `PAYMENT_PROVIDER=fake` (dev) : une page simule le paiement.
- `PAYMENT_PROVIDER=helloasso` (prod) : renseigner `HELLOASSO_CLIENT_ID`, `HELLOASSO_CLIENT_SECRET`, `HELLOASSO_ORGANIZATION_SLUG` dans `.env.local`. Commencer avec `HELLOASSO_API_URL=https://api.helloasso-sandbox.com`, puis `https://api.helloasso.com`. Les paiements de la soirée sont reconnaissables dans HelloAsso : libellé « Soirée USCL – … » (la boutique : « Boutique USCL – … ») et métadonnée `source=soiree`.
  La notification (`/paiement/helloasso/notification`) n'est qu'un filet de sécurité : le retour du payeur et le cron `app:booking:purge-pending` confirment aussi les paiements.
  Le paiement est toujours revérifié auprès de l'API. Aucune contribution volontaire n'est demandée.

## Tests

```bash
make test
```
