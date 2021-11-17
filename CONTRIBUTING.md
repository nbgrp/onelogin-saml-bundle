# Contributing

nb:group's OneLogin SAML Bundle is an open source, community-driven project.
If you'd like to contribute, feel free to do this, but remember about following rules.

## Branching strategy

- For bug fixes base your changes on the oldest maintainable branch.
- For new features without backward compatibility (BC) issues base your changes on the oldest
maintainable branch (if there is no reason to base it on another one).
- For new features with BC issues __always__ base your changes on the newest maintainable branch.
- When you create a pull request (PR), always select the branch your code based on as target.
- Before submitting your PR, please rebase your branch on the target branch.

## Standards

- Our code standard based on PSR-12, but includes more detailed rules.
- For code style checks, formatting and static analyze we use the following tools:
  - PHP-CS-Fixer
  - PHP_CodeSniffer
  - PHP Mess Detector
  - PHP Magic Number Detector
  - PHPStan
  - Phan
  - Psalm

### nb:group Auditor

For your convenience there is [nbgrp/auditor](https://hub.docker.com/r/nbgrp/auditor) docker
image that allows to run all necessary checks at once. It is based on the GrumPHP tool and used
on every push into the repository to validate it. You can run container based on this image (if you
have installed `docker`) by command

```composer nba```

## Tests

- You MUST run tests before push your code into the repository. You can do it by command <br>
`vensor/bin/simple-phpunit`
- You SHOULD write (or update) unit tests that cover your code.
- You MAY write (or update) bad unit tests.

## Documentation

You SHOULD write (or update) documentation.
