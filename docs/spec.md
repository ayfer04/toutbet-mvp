# Spec MVP ToutBet'

## Contexte
Plateforme mobile-first de paris entre particuliers. Un Bookie crée un pari,
fixe une mise min/max et une cotation, invite des Parieurs. Les fonds sont
séquestrés puis redistribués aux gagnants après validation du résultat,
moins la commission du Bookie et celle de la plateforme.

## Stack (versions stables et maintenues uniquement)
- Backend : Symfony 7.x LTS, PHP 8.3, Doctrine, PostgreSQL 16
- Front : Vue 3 + Vite, PWA
- Paiement : Stripe (mode test) derrière une interface PaymentGateway
- Docker Compose
Interdit : bibliothèque non maintenue, dépréciée ou non listée ici sans
justification écrite.

## User stories
1. En tant que Bookie, je veux pouvoir créer un pari afin de permettre aux parieurs de participer.
2. En tant que Bookie, je veux pouvoir inviter des parieurs afin qu'ils puissent participer à mon pari.
3. En tant que Bookie, je veux pouvoir définir une mise minimum et maximum ainsi qu'une cotation afin de gérer mon pari.
4. En tant que parieur, je veux pouvoir miser sur un pari afin de pouvoir y participer.
5. En tant que parieur, je veux pouvoir effectuer un paiement afin de pouvoir placer ma mise.
6. En tant que Bookie, je veux que l'argent des paris soit gardé par l'application jusqu'à la fin du pari.
7. En tant que Bookie, je veux pouvoir valider le résultat d'un pari afin de déterminer les gagnants.
8. En tant que Bookie, je veux pouvoir distribuer les gains aux gagnants afin de terminer le pari.
9. En tant que Bookie, je veux pouvoir consulter l'historique des paris et des gains afin de suivre les résultats.
10. En tant que parieur, je veux pouvoir créer un compte et me connecter afin de pouvoir utiliser l'application.

## Abuse stories à bloquer
1. En tant que parieur malveillant, je veux modifier ou supprimer un pari afin de fausser son résultat.
2. En tant qu'utilisateur malveillant, je veux créer de faux comptes afin de fausser les participations.
3. En tant que parieur malveillant, je veux modifier les mises ou les cotations afin d'augmenter mes gains.
4. En tant que parieur malveillant, je veux modifier ma mise après la clôture du pari afin de gagner plus d'argent.
5. En tant qu'attaquant, je veux récupérer les données bancaires des utilisateurs afin de les utiliser.
6. En tant qu'attaquant, je veux accéder à l'argent des paris afin de récupérer les mises.
7. En tant que personne malveillante, je veux modifier le résultat d'un pari afin de faire gagner la personne de mon choix.
8. En tant qu'attaquant, je veux modifier le bénéficiaire des gains afin de récupérer l'argent.
9. En tant qu'attaquant, je veux accéder à l'historique des utilisateurs afin de récupérer leurs informations.
10. En tant qu'attaquant, je veux accéder au compte d'un autre utilisateur afin de récupérer ses informations et ses fonds.

## Exigences de sécurité (issues du STRIDE)
### Moindre privilège
- Refus par défaut. Rôles ROLE_PARIEUR, ROLE_BOOKIE, vérifiés côté serveur
  par des Voters Symfony sur CHAQUE endpoint.
- L'utilisateur PostgreSQL de l'application n'a ni DROP ni droits admin.
- Un Bookie ne peut pas miser sur son propre pari.

### Broken Access Control (OWASP A01)
- Aucun accès par simple identifiant (anti-IDOR) : chaque ressource est
  filtrée par propriétaire.
- Résultat validable une seule fois, par le Bookie propriétaire, après clôture.
- Mise non modifiable après clôture ; cotation non modifiable après la 1re mise.

### Injection (SSRF, path traversal, SQL)
- Requêtes uniquement via Doctrine (paramètres liés), jamais de concaténation.
- Aucun appel HTTP sortant vers une URL fournie par l'utilisateur.
- Aucun chemin de fichier construit à partir d'une entrée utilisateur.
- Validation stricte de toutes les entrées (Symfony Validator).

### Authentification
- Argon2id, JWT 15 min + refresh token avec rotation,
  rate limiting sur /login.

### Argent
- Montants en centimes (entiers). Transactions SQL avec verrouillage.
- Distribution des gains idempotente. Webhooks Stripe : signature vérifiée.
- Aucune donnée de carte stockée.

### Traçabilité (non-répudiation)
- Journal d'audit append-only, horodaté, hash chaîné : création de pari,
  mise, validation de résultat, distribution.

### Disponibilité
- Rate limiting par IP et par utilisateur ; pagination obligatoire.

### Configuration sécurisée (ne PAS laisser les valeurs par défaut)
- En-têtes : Strict-Transport-Security, Content-Security-Policy stricte
  (sans unsafe-inline), X-Content-Type-Options, X-Frame-Options,
  Referrer-Policy.
- CORS limité à l'origine du front. APP_DEBUG=0 en prod.
- Secrets via variables d'environnement, .env.example sans vraies valeurs.

### Faux comptes (décision humaine, ajoutée après la 1re génération)
- Vérification email obligatoire avant de miser (lien à usage unique, 24 h)
- Rate limiting sur /api/register (5 par IP et par heure)
- Mise uniquement sur un pari auquel on est invité (invitation non révoquée)
