ifndef ENV
    ENV=dev
endif

COMMAND = docker-compose -f docker-compose.yml

start:
	@echo "==> START - Building $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml up -d --build

stop:
	@echo "==> STOP - Stopping $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml stop

up:
	@echo "==> UP - Building $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml up --build

down:
	@echo "==> DOWN - Removing $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml down


sync:
	@echo "==> SYNC - Executing sync command $(ENV)"
	@$(COMMAND) -f docker-compose.dev.yml run --rm --no-deps /usr/local/bin/php bin/console app:sync

histreset:
	@echo "==> HISTRESET - Executing history reset command $(ENV)"
	@$(COMMAND) -f docker-compose.dev.yml run --rm --no-deps /usr/local/bin/php bin/console app:reset

