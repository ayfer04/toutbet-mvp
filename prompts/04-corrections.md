# Journal des corrections et décisions

| # | Erreur / problème rencontré | Prompt envoyé / action | Résultat |
|---|---|---|---|
| 1 | GPT 5.2 demande `definition-of-done.md`, référencé dans le prompt mais non fourni | Envoi du contenu de `definition-of-done.md` et `stride.md` | Contexte complet, module auth généré |
| 2 | Abuse story "faux comptes" sans mitigation dans la spec ; le modèle refuse d'inventer | Décision humaine : vérification email + rate limiting inscription + mise sur invitation | Spec mise à jour (section "Faux comptes") |
| 3 | Limite d'usage du compte ChatGPT : ZIP des modules paris/invitations/mises non téléchargeable | Reprise avec Claude (voir `05-generation-claude.md`) | Modules générés et intégrés |
| 4 | Le module auth généré ne chargeait pas les fichiers `.env` et le `.gitignore` n'excluait pas `.env` (risque de fuite de secrets) | Ajout de `symfony/dotenv`, `tests/bootstrap.php`, `/.env` dans `.gitignore` | Corrigé |
| 5 | Les tests existants partageaient la même IP : les rate limiters les auraient fait échouer entre eux | IP aléatoire par test | Corrigé |
| 6 | Pas de point d'entrée d'authentification : une requête sans jeton pouvait produire une erreur 500 au lieu de 401 | `JwtAuthenticator` implémente `start()` (401 JSON) | Corrigé |
| 7 | Composer (Packagist) inaccessible depuis l'environnement de génération : PHPUnit non exécuté | Syntaxe PHP vérifiée, migrations testées sur PostgreSQL 16 réel ; tests délégués à la CI GitHub Actions | À vérifier dans l'onglet Actions |
