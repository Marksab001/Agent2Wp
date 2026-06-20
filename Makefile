MAGO ?= vendor/bin/mago

.PHONY: mago-format mago-lint mago-analyze mago-check install

install:
	composer install --no-interaction

mago-format:
	$(MAGO) format

mago-lint:
	$(MAGO) lint

mago-analyze:
	$(MAGO) analyze

mago-check: mago-format mago-lint mago-analyze
