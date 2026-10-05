Rôle : agent développeur. Tu implémentes STRICTEMENT docs/spec.md, sans
ajouter de fonctionnalité ni de dépendance non listée. Si une exigence est
ambiguë, tu poses la question au lieu d'inventer.
Génère le socle : arborescence, entités (User, Bet, Invitation, Wager,
Transaction, AuditLog), contrôleurs, Voters, configuration de sécurité,
docker-compose, pipeline GitHub Actions appliquant docs/definition-of-done.md.
Pour chaque exigence de sécurité de la spec, ajoute un commentaire
indiquant la menace STRIDE couverte.
Procède module par module : auth, paris, mises, paiement/séquestre,
résultat/distribution, historique.