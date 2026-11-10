#!/usr/bin/env bash
# Mise a jour de la prod (o2switch) : à lancer SUR LE SERVEUR.
#   ssh deme6348@... 'bash ~/uscl-soiree/bin/deploy.sh'
# Le serveur ne reçoit que du code déjà poussé sur origin/main : jamais de modification locale.
set -euo pipefail

PHP="${PHP:-/home/deme6348/bin/php84}"
cd "$(dirname "$0")/.."
[ "$(pwd)" = "/home/deme6348/uscl-soiree" ] || { echo "ABANDON : mauvais dossier ($(pwd))"; exit 1; }
[ -f .env.local ] || { echo "ABANDON : .env.local absent"; exit 1; }
grep -q '^APP_ENV=prod' .env.local || { echo "ABANDON : APP_ENV n'est pas prod"; exit 1; }

# Refuse de deployer si le serveur a des fichiers suivis modifiés à la main.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ABANDON : modifications locales sur le serveur :"; git status --short --untracked-files=no; exit 1
fi

BEFORE=$(git rev-parse --short HEAD)
git fetch -q origin main
AFTER=$(git rev-parse --short origin/main)
if [ "$BEFORE" = "$AFTER" ] && [ "${FORCE:-0}" != "1" ]; then
  echo "Deja a jour ($BEFORE). Rien a deployer (FORCE=1 pour relancer les etapes)."; exit 0
fi

echo "== Code : $BEFORE -> $AFTER"
git merge --ff-only origin/main
git log --oneline "$BEFORE..HEAD" 2>/dev/null | head -20 || true

echo "== Dependances"
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Migrations"
"$PHP" bin/console doctrine:migrations:migrate -n --env=prod

echo "== Assets"
"$PHP" bin/console importmap:install --env=prod
"$PHP" bin/console assets:install public --env=prod
"$PHP" bin/console asset-map:compile --env=prod

echo "== Cache"
"$PHP" bin/console cache:clear --env=prod

echo "OK : prod a $AFTER (precedent : $BEFORE, retour arriere : git reset --hard $BEFORE + migrations a la main si besoin)"
