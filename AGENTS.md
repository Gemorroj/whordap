# AGENTS.md

## Project

WhoRdap is a PHP library for WHOIS/RDAP lookups (domains, IPv4/IPv6, CIDR, ASN).

- Namespace: `WhoRdap\` -> `src/`
- Tests namespace: `WhoRdap\Tests\` -> `tests/`
- PHP >= 8.4

## Commands

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
```

## Conventions

- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).

## Rules

- Follow PHP-CS-Fixer ruleset from `.php-cs-fixer.dist.php` (@Symfony, @PHP8x4Migration).
- Native functions/constants with leading backslash (`\strlen()`, `\PHP_EOL`).
- Strict comparison (`===`, `!==`).
- Run tests, php-cs-fixer, and phpstan before considering work complete.
- Never commit unless explicitly asked.
