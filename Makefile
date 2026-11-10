DC = docker compose -f .docker/docker-compose.yml --env-file .docker/.env
CONSOLE = .docker/bin/console

## Démarre l'environnement (http://localhost:8190, admin : /admin)
up:
	@$(DC) up -d

down:
	@$(DC) down

## Installation complète : dépendances, base, données de démo
init: up composer-install database-init

composer-install:
	@.docker/bin/composer install

database-init:
	@$(CONSOLE) doctrine:database:drop --force --if-exists
	@$(CONSOLE) doctrine:database:create
	@$(CONSOLE) -n doctrine:migrations:migrate

migration:
	@$(CONSOLE) make:migration

migrate:
	@$(CONSOLE) -n doctrine:migrations:migrate

test:
	@$(CONSOLE) --env=test -n doctrine:database:create --if-not-exists
	@$(CONSOLE) --env=test -n doctrine:migrations:migrate
	@.docker/bin/php vendor/bin/phpunit
