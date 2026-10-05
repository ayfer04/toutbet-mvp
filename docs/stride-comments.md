# Couverture STRIDE du module Auth

- **Spoofing** : authentification par email + mot de passe Argon2id ; JWT court de 15 min ; rotation des refresh tokens ; limitation de `/api/login`.
- **Elevation of Privilege** : rôles refusés par défaut et vérifiés côté serveur par le mécanisme de sécurité Symfony ; aucun rôle n'est accepté depuis le payload utilisateur.
- **Information Disclosure** : aucune donnée de carte stockée ; le mot de passe n'est jamais renvoyé ; le refresh token brut n'est jamais stocké en base.
- **Denial of Service** : rate limiting sur `/api/login`.
