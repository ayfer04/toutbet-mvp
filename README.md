# ToutBet' — MVP sécurisé

Plateforme mobile-first de paris personnalisés entre particuliers (Atelier 1 — Conception sécurisée et modélisation des menaces).
Socle backend généré en vibe coding (GPT 5.2 puis Claude), guidé par une spec et des prompts historisés.

## Contenu du dépôt
- `docs/spec.md` : spécification (user stories, abuse stories, exigences de sécurité)
- `docs/stride.md` : modélisation STRIDE
- `docs/definition-of-done.md` : critères de sécurité de la DoD
- `prompts/` : prompts utilisés, par rôle d'agent, et journal des corrections
- `src/`, `tests/`, `migrations/` : code Symfony 7 / PHP 8.3 / PostgreSQL 16
- `.github/workflows/ci.yml` : tests, PHPStan, `composer audit`, gitleaks

## Lancer le projet
```bash
cp .env.example .env        # puis remplacer chaque valeur "replace-..."
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php bin/console doctrine:migrations:migrate -n
```

## API
| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/register`, `/api/verify-email`, `/api/login`, `/api/refresh` | public |
| POST | `/api/bets` | Bookie |
| GET | `/api/bets`, `/api/bets/{id}` | Bookie propriétaire ou invité |
| PATCH | `/api/bets/{id}` (cotation, plafonds) | Bookie propriétaire, avant la 1re mise |
| POST | `/api/bets/{id}/invitations` · DELETE `/api/bets/{id}/invitations/{invId}` | Bookie propriétaire |
| POST | `/api/bets/{id}/close` · `/api/bets/{id}/result` | Bookie propriétaire |
| POST | `/api/bets/{id}/wagers` | parieur invité, email vérifié |
| POST | `/api/payments/deposits` · `/api/webhooks/payment` (signé) | connecté · prestataire |
| GET | `/api/me/wagers`, `/api/me/transactions` | soi-même uniquement |

## Menace STRIDE → mesure implémentée
| STRIDE | Menace | Mesure | Fichier |
|---|---|---|---|
| Spoofing | Usurpation de compte | Argon2id, JWT 15 min, refresh token rotatif haché, rate limiting login | `AuthController`, `JwtService`, `RefreshTokenService` |
| Spoofing | Faux comptes | Email vérifié (24 h, usage unique) + 5 inscriptions/IP/h + mise sur invitation | `EmailVerificationService`, `WagerService` |
| Spoofing | Faux webhook de paiement | Signature HMAC + fenêtre anti-rejeu 5 min | `WebhookSignatureVerifier` |
| Tampering | Mise modifiée après clôture | Mise immuable (aucune route, trigger SQL), verrou pessimiste, contrôle de clôture | `Wager`, `WagerService`, migration |
| Tampering | Résultat falsifié | Bookie propriétaire, après clôture, une seule fois | `Bet::settle`, `BetVoter` |
| Tampering | Cotation / plafonds modifiés | Figés après la 1re mise | `Bet::updateTerms` |
| Tampering | Bénéficiaire des gains détourné | Bénéficiaire = auteur de la mise, jamais un paramètre | `SettlementService` |
| Repudiation | Nier un pari / une validation | Journal append-only, hash chaîné, trigger SQL anti-UPDATE/DELETE | `AuditLogger`, `AuditLog` |
| Information disclosure | Données de carte | Aucune donnée de carte stockée (prestataire uniquement) | `PaymentGateway` |
| Information disclosure | IDOR historique / paris | Historique = utilisateur connecté ; 404 si non autorisé | `HistoryController`, `BetVoter` |
| Denial of service | Flood / bots | Rate limiting login, inscription, actions par utilisateur ; pagination max 50 | `rate_limiter.yaml`, `Json::pagination` |
| Elevation of privilege | Parieur → Bookie / admin | Voters, refus par défaut, rôles jamais lus depuis le client ou le JWT | `BetVoter`, `security.yaml` |
| Configuration | En-têtes, CORS | HSTS, CSP sans unsafe-inline, nosniff, DENY, CORS limité à `FRONTEND_ORIGIN` | `SecurityHeadersSubscriber`, `CorsSubscriber` |

## Règle de répartition des gains
Pot = somme des mises. Commission plateforme 5 % et commission du Bookie (0 à 10 %, fixée à la création) prélevées,
le reste partagé entre gagnants au prorata. Aucun gagnant : remboursement intégral. Montants en centimes, arrondis à la plateforme.
La cotation est enregistrée et figée à chaque mise (information affichée au parieur).

## Limites connues (à traiter avant production)
- Transport email du lien de vérification et adaptateur Stripe réel : interfaces prêtes, implémentations de test seulement.
- Front Vue 3 / PWA non généré.
- Revue de sécurité humaine (Security Champion) à effectuer avant toute mise en production.
