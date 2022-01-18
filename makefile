
PHONY: quality
quality: cs-fix test


PHONY: cs-fix
cs-fix:
	bin/php-cs-fixer fix

PHONY: test
test:
	bin/phpunit