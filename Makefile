.PHONY: help init-env build up rebuild down restart ps logs smoke smoke-debezium queue-depth debezium-logs consumer-logs

DOCKER_COMPOSE := docker compose

help:
	@echo "Available targets:"
	@echo "  make init-env - Create missing local env files from examples"
	@echo "  make build    - Build all containers"
	@echo "  make up       - Start the full local stack"
	@echo "  make rebuild  - Rebuild images and recreate the stack"
	@echo "  make down     - Stop the stack"
	@echo "  make restart  - Recreate and restart the stack"
	@echo "  make ps       - Show container status"
	@echo "  make logs     - Tail logs for the stack"
	@echo "  make smoke    - Run Kong smoke test"
	@echo "  make smoke-debezium - Run end-to-end Debezium smoke test"
	@echo "  make queue-depth    - Show RabbitMQ queue depth for the orders queue"
	@echo "  make debezium-logs  - Tail Debezium logs"
	@echo "  make consumer-logs  - Tail inventory order consumer logs"

init-env:
	@test -f .env || cp .env.example .env
	@test -f apps/auth-service/.env || cp apps/auth-service/.env.example apps/auth-service/.env
	@test -f apps/inventory-service/.env || cp apps/inventory-service/.env.example apps/inventory-service/.env
	@test -f apps/order-service/.env || cp apps/order-service/.env.example apps/order-service/.env

build:
	$(DOCKER_COMPOSE) build

up: init-env
	$(DOCKER_COMPOSE) up -d

rebuild: init-env
	$(DOCKER_COMPOSE) up -d --build --force-recreate

down:
	$(DOCKER_COMPOSE) down

restart: init-env
	$(DOCKER_COMPOSE) up -d --force-recreate

ps:
	$(DOCKER_COMPOSE) ps

logs:
	$(DOCKER_COMPOSE) logs -f --tail=100

smoke:
	./scripts/smoke-test-kong.sh

smoke-debezium:
	./scripts/smoke-test-debezium.sh

queue-depth:
	./scripts/queue-depth.sh

debezium-logs:
	$(DOCKER_COMPOSE) logs -f --tail=100 debezium

consumer-logs:
	$(DOCKER_COMPOSE) logs -f --tail=100 inventory-order-consumer
