# Modèle de données ToutBet'

## Entités

### User
- `id` UUID, identifiant interne
- `email` string unique, normalisé en minuscules
- `passwordHash` string, Argon2id
- `roles` JSON, `ROLE_PARIEUR` par défaut ; `ROLE_BOOKIE` en complément pour un Bookie
- `refreshTokenHash` nullable string
- `refreshTokenExpiresAt` nullable datetime immutable
- `createdAt` datetime immutable

Relations :
- `User 1—N Bet` : Bookie propriétaire du pari
- `User 1—N Invitation` : expéditeur et destinataire
- `User 1—N Wager` : parieur
- `User 1—N Transaction` : utilisateur concerné
- `User 1—N AuditLog` : auteur de l'action

### Bet
- `id` UUID
- `bookie` FK User
- `title` string
- `description` nullable text
- `minStakeCents` int
- `maxStakeCents` int
- `odds` decimal
- `status` enum métier à définir lors du module paris
- `closesAt` datetime immutable
- `resultValidatedAt` nullable datetime immutable
- `createdAt` datetime immutable

### Invitation
- `id` UUID
- `bet` FK Bet
- `inviter` FK User
- `invitee` FK User
- `status` enum métier à définir lors du module paris
- `createdAt` datetime immutable

### Wager
- `id` UUID
- `bet` FK Bet
- `bettor` FK User
- `stakeCents` int
- `oddsSnapshot` decimal
- `createdAt` datetime immutable
- `closedAt` nullable datetime immutable

### Transaction
- `id` UUID
- `user` FK User
- `bet` nullable FK Bet
- `wager` nullable FK Wager
- `type` enum métier à définir lors du module paiement
- `amountCents` int
- `externalReference` nullable string
- `createdAt` datetime immutable

### AuditLog
- `id` UUID
- `actor` nullable FK User
- `action` string
- `entityType` string
- `entityId` string
- `payload` JSON
- `createdAt` datetime immutable
- `previousHash` nullable string
- `hash` string

## Relations principales

```text
User (Bookie) 1 ─── N Bet
User 1 ─── N Invitation (inviter)
User 1 ─── N Invitation (invitee)
Bet 1 ─── N Invitation
User (Parieur) 1 ─── N Wager
Bet 1 ─── N Wager
User 1 ─── N Transaction
Bet 1 ─── N Transaction
Wager 1 ─── N Transaction
User 1 ─── N AuditLog
```

> Les règles métier détaillées des statuts, invitations, transactions et distributions sont volontairement réservées aux modules concernés : elles ne sont pas inventées dans le module d'authentification.
