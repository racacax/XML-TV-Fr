
PHONY: quality
quality: cs-fix phpstan test


PHONY: cs-fix
cs-fix:
	bin/php-cs-fixer fix

PHONY: test
test:
	bin/phpunit

PHONY: phpstan
phpstan:
	bin/phpstan

drun:
	docker run -v ./manager.php:/app/manager.php -v ./var:/app/var -v ./config/:/app/config -v ./src:/app/src -v ./integrity.sha256:/app/integrity.sha256 -v ./resources:/app/resources -v ./commands:/app/commands -v ./tests:/app/tests xmltvfr $(ARGS)