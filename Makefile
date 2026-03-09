ifneq ($(shell docker compose version 2>/dev/null),)
  DOCKER_COMPOSE=docker compose
else
  DOCKER_COMPOSE=docker-compose
endif

## All these make targets (commands) are only useful for a Docker environment!

# Master command to build and start everything
start: build up apply

debug: export XDEBUG_MODE=debug,develop
debug: build up apply

# Builds containers (init .env while at it)
build:
	[ ! -f ./.env ] && cp -p -v ./.env.dockerinit ./.env || true
	$(DOCKER_COMPOSE) build

down:
	$(DOCKER_COMPOSE) down

# Starts containers in the background
up:
	$(DOCKER_COMPOSE) up -d

# Applies changes (dependencies, migrations) to running containers
apply: composer-install migrate

# Runs composer install (updates dependencies)
composer-install:
	$(DOCKER_COMPOSE) exec platform util wait_bootstrap
	$(DOCKER_COMPOSE) exec platform util run_composer_install
	$(DOCKER_COMPOSE) exec platform_tasks util wait_bootstrap
	$(DOCKER_COMPOSE) exec platform_tasks util run_composer_install

# Runs database migrations
migrate:
	$(DOCKER_COMPOSE) exec platform util wait_bootstrap
	$(DOCKER_COMPOSE) exec platform util run_migrations

# Tails logs on the screen
logs:
	$(DOCKER_COMPOSE) logs -f

enter:
	$(DOCKER_COMPOSE) exec platform bash

pre-test:
	$(DOCKER_COMPOSE) exec platform composer run pre-test

test: export XDEBUG_MODE=coverage
test:
	$(DOCKER_COMPOSE) exec platform composer run test-dev

pre-push-test:
	$(DOCKER_COMPOSE) exec platform composer run pre-push-test

test-ci:
	$(DOCKER_COMPOSE) exec platform composer run test

cleanup:
	$(DOCKER_COMPOSE) exec platform composer run fixlint

stop:
	$(DOCKER_COMPOSE) stop

# Prints instructions for running the mzima client accessible from the local network.
# The Angular dev server only binds to localhost by default; --host 0.0.0.0 fixes that.
local-client-help:
	@HOST_IP=$$(hostname -I 2>/dev/null | awk '{print $$1}' || ipconfig getifaddr en0 2>/dev/null || echo "<your-machine-ip>"); \
	echo ""; \
	echo "=== Local-network development instructions ==="; \
	echo ""; \
	echo "Your Platform API is accessible at:"; \
	echo "  http://$$HOST_IP:8080   (from other devices on the local network)"; \
	echo "  http://localhost:8080    (from this machine)"; \
	echo ""; \
	echo "To run the mzima client accessible from other devices, go into your"; \
	echo "platform-client-mzima directory and run:"; \
	echo ""; \
	echo "  npm run web:serve -- --host 0.0.0.0"; \
	echo ""; \
	echo "Make sure the client's .env file has:"; \
	echo "  BACKEND_URL=http://$$HOST_IP:8080"; \
	echo ""
