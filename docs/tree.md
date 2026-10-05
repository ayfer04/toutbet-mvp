# Arborescence complète prévue

```text
toutbet/
├── .env.example
├── .env.test
├── composer.json
├── docker-compose.yml
├── phpunit.xml.dist
├── bin/
│   └── console
├── config/
│   ├── bundles.php
│   ├── routes.yaml
│   ├── services.yaml
│   └── packages/
│       ├── doctrine.yaml
│       ├── framework.yaml
│       ├── rate_limiter.yaml
│       └── security.yaml
├── docs/
│   ├── definition-of-done.md
│   ├── model.md
│   ├── stride-comments.md
│   └── tree.md
├── migrations/
├── src/
│   ├── Controller/
│   │   └── AuthController.php
│   ├── Entity/
│   │   ├── User.php
│   │   ├── Bet.php
│   │   ├── Invitation.php
│   │   ├── Wager.php
│   │   ├── Transaction.php
│   │   └── AuditLog.php
│   ├── EventSubscriber/
│   │   └── SecurityHeadersSubscriber.php
│   ├── Repository/
│   │   └── UserRepository.php
│   ├── Security/
│   │   └── JwtAuthenticator.php
│   ├── Service/
│   │   ├── JwtService.php
│   │   └── RefreshTokenService.php
│   └── Kernel.php
├── tests/
│   ├── Unit/Service/
│   │   └── JwtServiceTest.php
│   └── Functional/
│       ├── Controller/
│       │   └── AuthControllerTest.php
│       └── Security/
│           └── AuthAbuseStoriesTest.php
└── public/
    └── index.php
```

`public/index.php` est à placer sous `public/` dans le projet réel ; il est représenté ici comme fichier de bootstrap HTTP.
