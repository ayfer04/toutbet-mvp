Rôle : agent testeur. Écris des tests PHPUnit pour chaque abuse story de
docs/spec.md : mise après clôture, validation par un non-propriétaire,
double validation, accès à l'historique d'un autre utilisateur,
modification de cotation après une mise, Bookie qui mise sur son pari,
webhook Stripe sans signature valide, dépassement du rate limit.
Chaque test doit échouer si la protection est retirée.