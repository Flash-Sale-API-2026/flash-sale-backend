.PHONY: help build up rebuild down restart ps logs smoke

DOCKER_COMPOSE := docker compose

help:
	@echo "Available targets:"
	@echo "  make build    - Build all containers"
	@echo "  make up       - Start the full local stack"
	@echo "  make rebuild  - Rebuild images and recreate the stack"
	@echo "  make down     - Stop the stack"
	@echo "  make restart  - Recreate and restart the stack"
	@echo "  make ps       - Show container status"
	@echo "  make logs     - Tail logs for the stack"
	@echo "  make smoke    - Run Kong smoke test"

build:
	$(DOCKER_COMPOSE) build

up:
	$(DOCKER_COMPOSE) up -d

rebuild:
	$(DOCKER_COMPOSE) up -d --build --force-recreate

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) up -d --force-recreate

ps:
	$(DOCKER_COMPOSE) ps

logs:
	$(DOCKER_COMPOSE) logs -f --tail=100

smoke:
	./scripts/smoke-test-kong.sh
