
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
